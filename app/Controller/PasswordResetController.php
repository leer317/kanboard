<?php

namespace Kanboard\Controller;

use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * Password Reset Controller
 *
 * @package Kanboard\Controller
 * @author  Frederic Guillot
 */
class PasswordResetController extends BaseController
{
    /**
     * Show the form to reset the password
     */
    public function create(array $values = array(), array $errors = array())
    {
        $this->checkActivation();

        $this->response->html($this->helper->layout->app('password_reset/create', array(
            'errors' => $errors,
            'values' => $values,
            'no_layout' => true,
        )));
    }

    /**
     * Validate and send the email
     */
    public function save()
    {
        $this->checkActivation();

        $values = $this->request->getValues();
        list($valid, $errors) = $this->passwordResetValidator->validateCreation($values);

        if ($valid) {
            $this->sendEmail($values['username']);
            $this->response->redirect($this->helper->url->to('AuthController', 'login'));
        } else {
            $this->create($values, $errors);
        }
    }

    /**
     * Show the form to set a new password
     */
    public function change(array $values = array(), array $errors = array())
    {
        $this->checkActivation();

        $token = $this->request->getStringParam('token');
        $user_id = $this->passwordResetModel->getUserIdByToken($token);

        if ($user_id !== false) {
            $this->response->html($this->helper->layout->app('password_reset/change', array(
                'token' => $token,
                'errors' => $errors,
                'values' => $values,
                'no_layout' => true,
            )));
        } else {
            $this->response->redirect($this->helper->url->to('AuthController', 'login'));
        }
    }

    /**
     * Set the new password
     */
    public function update()
    {
        $this->checkActivation();

        $token = $this->request->getStringParam('token');
        $values = $this->request->getValues();
        list($valid, $errors) = $this->passwordResetValidator->validateModification($values);

        if ($valid) {
            $user_id = $this->passwordResetModel->getUserIdByToken($token);

            if ($user_id !== false) {
                $this->userModel->update(array('id' => $user_id, 'password' => $values['password']));
                $this->passwordResetModel->disable($user_id);
            }

            return $this->response->redirect($this->helper->url->to('AuthController', 'login'));
        }

        return $this->change($values, $errors);
    }

    /**
     * Send the email
     */
    private function sendEmail($username)
    {
        $token = $this->passwordResetModel->create($username);

        if ($token !== false) {
            $user = $this->userModel->getByUsername($username);

            $this->emailClient->send(
                $user['email'],
                $user['name'] ?: $user['username'],
                t('Password Reset for Kanboard'),
                $this->template->render('password_reset/email', array('token' => $token))
            );
        }
    }

    /**
     * Check feature availability
     */
    private function checkActivation()
    {
        if ($this->configModel->get('password_reset', 0) == 0) {
            throw AccessForbiddenException::getInstance()->withoutLayout();
        }
    }
}
