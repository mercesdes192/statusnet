<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Login form
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Login
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Login form
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class LoginAction extends Action
{
    /**
     * Has there been an error?
     */

    var $error = null;

    /**
     * Is this a read-only action?
     *
     * @return boolean false
     */

    function isReadOnly($args)
    {
        return false;
    }

    /**
     * Handle input, produce output
     *
     * Switches on request method; either shows the form or handles its input.
     *
     * @param array $args $_REQUEST data
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        $disabled = common_config('logincommand','disabled');

        if (common_is_real_login()) {
            $this->clientError(_('Already logged in.'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->checkLogin();
        } else if (!isset($disabled) && isset($args['user_id']) && isset($args['token'])){
            $this->checkLogin($args['user_id'],$args['token']);
        } else {
            common_ensure_session();
            $this->showForm();
        }
    }

    /**
     * Check the login data
     *
     * Determines if the login data is valid. If so, logs the user
     * in, and redirects to the 'with friends' page, or to the stored
     * return-to URL.
     *
     * @return void
     */

    function checkLogin($user_id=null, $token=null)
    {
        if(isset($token) && isset($user_id)){
            //Token based login (from the LoginCommand)
            $login_token = Login_token::staticGet('user_id',$user_id);
            if($login_token && $login_token->token == $token){
                if($login_token->modified > time()+2*60){
                    //token has expired
                    //delete the token as it is useless
                    $login_token->delete();
                    $this->showForm(_('Invalid or expired token.'));
                    return;
                }else{
                    //delete the token so it cannot be reused
                    $login_token->delete();
                    //it's a valid token - let them log in
                    $user = User::staticGet('id', $user_id);
                    //$user = User::staticGet('nickname', "candrews");
                }
            }else{
                $this->showForm(_('Invalid or expired token.'));
                return;
            }
        }else{
            // Regular form submission login

            // XXX: login throttle

            // CSRF protection - token set in NoticeForm
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->clientError(_('There was a problem with your session token. '.
                                     'Try again, please.'));
                return;
            }

            $nickname = $this->trimmed('nickname');
            $password = $this->arg('password');

            $user = common_check_user($nickname, $password);
        }

        if (!$user) {
            $this->showForm(_('Incorrect username or password.'));
            return;
        }

        // success!
        if (!common_set_user($user)) {
            $this->serverError(_('Error setting user. You are probably not authorized.'));
            return;
        }

        common_real_login(true);

        if ($this->boolean('rememberme')) {
            common_rememberme($user);
        }

        $url = common_get_returnto();

        if ($url) {
            // We don't have to return to it again
            common_set_returnto(null);
        } else {
            $url = common_local_url('all',
                                    array('nickname' =>
                                          $user->nickname));
        }

        common_redirect($url, 303);
    }

    /**
     * Store an error and show the page
     *
     * This used to show the whole page; now, it's just a wrapper
     * that stores the error in an attribute.
     *
     * @param string $error error, if any.
     *
     * @return void
     */

    function showForm($error=null)
    {
        $this->error = $error;
        $this->showPage();
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('nickname');
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */

    function title()
    {
        return _('Login');
    }

    /**
     * Show page notice
     *
     * Display a notice for how to use the page, or the
     * error if it exists.
     *
     * @return void
     */

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('p', 'error', $this->error);
        } else {
            $instr  = $this->getInstructions();
            $output = common_markup_to_html($instr);

            $this->raw($output);
        }
    }

    /**
     * Core of the display code
     *
     * Shows the login form.
     *
     * @return void
     */

    function showContent()
    {
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_login',
                                           'class' => 'form_settings',
                                           'action' => common_local_url('login')));
        $this->elementStart('fieldset');
        $this->element('legend', null, _('Login to site'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('nickname', _('Nickname'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->password('password', _('Password'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('rememberme', _('Remember me'), false,
                        _('Automatically login in the future; ' .
                           'not for shared computers!'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('submit', _('Login'));
        $this->hidden('token', common_session_token());
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
        $this->elementStart('p');
        $this->element('a', array('href' => common_local_url('recoverpassword')),
                       _('Lost or forgotten password?'));
        $this->elementEnd('p');
    }

    /**
     * Instructions for using the form
     *
     * For "remembered" logins, we make the user re-login when they
     * try to change settings. Different instructions for this case.
     *
     * @return void
     */

    function getInstructions()
    {
        if (common_logged_in() && !common_is_real_login() &&
            common_get_returnto()) {
            // rememberme logins have to reauthenticate before
            // changing any profile settings (cookie-stealing protection)
            return _('For security reasons, please re-enter your ' .
                     'user name and password ' .
                     'before changing your settings.');
        } else {
            return _('Login with your username and password. ' .
                     'Don\'t have a username yet? ' .
                     '[Register](%%action.register%%) a new account.');
        }
    }

    /**
     * A local menu
     *
     * Shows different login/register actions.
     *
     * @return void
     */

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}
