<?php
/**
 * @copyright Bluz PHP Team
 * @link https://github.com/bluzphp/skeleton
 */

/**
 * @namespace
 */
namespace Application\Users;

use Application\Auth;
use Application\Exception;
use Application\UsersActions;
use Bluz\Crud\ValidationException;

/**
 * Crud
 *
 * @package  Application\Users
 *
 * @method   Table getTable()
 *
 * @author   Anton Shevchuk
 * @created  30.10.12 16:11
 */
class Crud extends \Bluz\Crud\Table
{
    /**
     * @param $data
     * @throws \Application\Exception
     * @return integer
     */
    public function createOne($data)
    {
        $this->validate(null, $data);
        $this->validateCreate($data);
        $this->checkErrors();

        /** @var $row Row */
        $row = $this->getTable()->create();
        $row->setFromArray($data);
        $row->status = Table::STATUS_PENDING;
        $row->save();

        $userId = $row->id;

        // create auth
        $password = isset($data['password'])?$data['password']:null;
        Auth\Table::getInstance()->generateEquals($row, $password);

        // create activation token
        // valid for 5 days
        $actionRow = UsersActions\Table::getInstance()->generate($userId, UsersActions\Table::ACTION_ACTIVATION, 5);

        // send activation email
        // generate activation URL
        $activationUrl = app()->getRouter()->getFullUrl(
            'users',
            'activation',
            ['code' => $actionRow->code, 'id' => $userId]
        );

        $subject = "Activation";

        $body = app()->dispatch(
            'users',
            'mail-template',
            [
                'template' => 'registration',
                'vars' => ['user' => $row, 'activationUrl' => $activationUrl, 'password' => $password]
            ]
        )->render();

        try {
            $mail = app()->getMailer()->create();

            // subject
            $mail->Subject = $subject;
            $mail->MsgHTML(nl2br($body));

            $mail->AddAddress($data['email']);

            app()->getMailer()->send($mail);

        } catch (\Exception $e) {
            app()->getLogger()->log(
                'error',
                $e->getMessage(),
                ['module' => 'users', 'controller' => 'change-email', 'userId' => $userId]
            );

            throw new Exception('Unable to send email. Please contact administrator.');
        }

        // show notification and redirect
        app()->getMessages()->addSuccess(
            "Your account has been created and an activation link has".
            "been sent to the e-mail address you entered.<br/>".
            "Note that you must activate the account by clicking on the activation link".
            "when you get the e-mail before you can login."
        );
        app()->redirectTo('index', 'index');

        return $userId;
    }

    /**
     * @throws ValidationException
     */
    public function validateCreate($data)
    {
        // login
        $this->checkLogin($data);

        $login = isset($data['login'])?$data['login']:null;
        // check unique
        if ($this->getTable()->findRowWhere(['login' => $login])) {
            $this->addError(
                __('User with login "%s" already exists', esc($login)),
                'login'
            );
        }

        // email
        $this->checkEmail($data);

        $email = isset($data['email'])?$data['email']:null;
        // TODO: add solution for check gmail accounts (because a.s.d@gmail.com === asd@gmail.com)
        // check unique
        if ($this->getTable()->findRowWhere(['email' => $email])) {
            $this->addError(
                __('User with email "%s" already exists', esc($email)),
                'email'
            );
        }

        // password
        $password = isset($data['password'])?$data['password']:null;
        $password2 = isset($data['password2'])?$data['password2']:null;
        if (empty($password)) {
            $this->addError('Password can\'t be empty', 'password');
        }

        if ($password !== $password2) {
            $this->addError('Password is not equal', 'password2');
        }
    }

    /**
     * @throws ValidationException
     */
    public function validateUpdate($id, $data)
    {
        // name validator
        $this->checkLogin($data);

        // email validator
        $this->checkEmail($data);
    }

    /**
     * checkLogin
     *
     * @param $data
     * @return void
     */
    protected function checkLogin($data)
    {
        $login = isset($data['login'])?$data['login']:null;
        if (empty($login)) {
            $this->addError('Login can\'t be empty', 'login');
        }
        if (strlen($login) > 255) {
            $this->addError('Login can\'t be bigger than 255 symbols', 'login');
        }
    }

    /**
     * checkEmail
     *
     * @param array $data
     * @return boolean
     */
    public function checkEmail($data)
    {
        $email = isset($data['email'])?$data['email']:null;

        if (empty($email)) {
            $this->addError('Email can\'t be empty', 'email');
            return false;
        }

        if (strlen($email) > 255) {
            $this->addError('Email can\'t be bigger than 255 symbols', 'email');
            return false;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            list(, $domain) = explode("@", $email, 2);
            if (!checkdnsrr($domain, "MX") && !checkdnsrr($domain, "A")) {
                $this->addError('Email has invalid domain name', 'email');
                return false;
            }
        } else {
            $this->addError('Email is invalid', 'email');
            return false;
        }
        return true;
    }
}
