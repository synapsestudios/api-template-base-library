<?php

namespace Synapse\User;

use Symfony\Component\HttpFoundation\Request;
use Synapse\Controller\AbstractRestController;
use Synapse\Security\SecurityAwareInterface;
use Synapse\Security\SecurityAwareTrait;
use OutOfBoundsException;

/**
 * Controller for user related actions
 */
class UserController extends AbstractRestController implements SecurityAwareInterface
{
    use SecurityAwareTrait;

    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var UserValidator
     */
    protected $userValidator;

    /**
     * Return a user entity
     *
     * @param  Request $request
     * @return array
     */
    public function get(Request $request)
    {
        $user = $this->user();

        return $this->userArrayWithoutPassword($user);
    }

    /**
     * Create a user
     *
     * @param  Request $request
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function post(Request $request)
    {
        $userData = $this->getContentAsArray($request);

        $errors = $this->userValidator->validate($userData ?: []);

        if (count($errors) > 0) {
            return $this->createConstraintViolationResponse($errors);
        }

        try {
            $newUser = $this->userService->register($userData);
        } catch (OutOfBoundsException $e) {
            $httpCodes = [
                UserService::EMAIL_NOT_UNIQUE => 409,
            ];

            return $this->createSimpleResponse($httpCodes[$e->getCode()], $e->getMessage());
        }

        $newUser = $this->userArrayWithoutPassword($newUser);

        $newUser['_href'] = $this->url('user-entity', array('id' => $newUser['id']));

        return $this->createJsonResponse(201, $newUser);
    }

    /**
     * Edit a user; requires the user to be logged in and the current password provided
     *
     * @param  Request $request
     * @return array
     */
    public function put(Request $request)
    {
        $user = $this->user();

        $userValidationCopy = clone $user;

        // Validate the modified fields
        $errors = $this->userValidator->validate(
            $userValidationCopy->exchangeArray($this->getContentAsArray($request) ?: [])->getArrayCopy()
        );

        if (count($errors) > 0) {
            return $this->createConstraintViolationResponse($errors);
        }

        try {
            $user = $this->userService->update($user, $this->getContentAsArray($request));
        } catch (OutOfBoundsException $e) {
            $httpCodes = [
                UserService::CURRENT_PASSWORD_REQUIRED => 403,
                UserService::FIELD_CANNOT_BE_EMPTY     => 422,
                UserService::EMAIL_NOT_UNIQUE          => 409,
            ];

            return $this->createSimpleResponse($httpCodes[$e->getCode()], $e->getMessage());
        }

        return $this->userArrayWithoutPassword($user);
    }

    /**
     * @param UserService $service
     */
    public function setUserService(UserService $service)
    {
        $this->userService = $service;
        return $this;
    }

    /**
     * @param UserValidator $validator
     */
    public function setUserValidator(UserValidator $validator)
    {
        $this->userValidator = $validator;
        return $this;
    }

    /**
     * Transform the User entity into an array and remove the password element
     *
     * @param  User   $user
     * @return array
     */
    protected function userArrayWithoutPassword(UserEntity $user)
    {
        $user = $user->getArrayCopy();

        unset($user['password']);

        return $user;
    }
}
