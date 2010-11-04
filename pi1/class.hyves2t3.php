<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2010 Ron Schoellmann <ron@netsinn.de>,
 *           Joost van Berckel <joost@contentonline.nl>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */
require_once(PATH_tslib . 'class.tslib_pibase.php');
require_once(t3lib_extmgm::extPath('sociallogin2t3', 'lib/hyves/GenusApis.php'));

/**
 *
 * @author	Ron Schoellmann <ron@netsinn.de>, Joost van Berckel <joost@contentonline.nl>
 * @package	TYPO3
 * @subpackage	tx_sociallogin2t3
 */
class Hyves2t3 extends Sociallogin2t3_Base
{

    var $serviceProvider = "hyves";
    var $debug = false;
    var $scriptUrl;
    var $haVersion = "1.2.1";

    function __construct($conf)
    {
        $this->conf = $conf;
        $this->pi_setPiVarDefaults();
        $this->pi_loadLL();

        if ($this->debug)
        {
            echo '<pre>$conf:';
            print_r($conf);
            echo '</pre><hr/>';
        }

        $this->fields4Fetch = explode(',', $this->conf['fields4Fetch']);
        foreach ($this->fields4Fetch as $key => $value)
        {
            $this->fields4Fetch[$key] = trim($value);
        }

        $this->fe_usersFields = explode(',', $this->conf['fe_usersFields']);
        foreach ($this->fe_usersFields as $key => $value)
        {
            $this->fe_usersFields[$key] = trim($value);
        }

        $this->checkServicePrerequisites();

        $user = $this->doOauthRequest();

        // Fill value arrays:
        if ($user)
        {//[url] => http://username.hyves.nl/
            foreach ($this->fields4Fetch as $key => $value)
            {
                //$this->serviceValues[$key] = $user[$value];
                $this->serviceValues[$key] = (string) $user->{$value};
            }
            $this->fe_usersValues = array_combine($this->fe_usersFields, $this->serviceValues);
            $this->fe_usersValues['tx_sociallogin2t3_id'] = (string) $user->userid;
            $this->serviceUserId = (string) $user->userid;
            if ($this->serviceUserId && $this->serviceUserId != '') $this->storeUser();
        }

    }

    /**
     * OAuth stuff
     *
     * @return array user info
     */
    function doOauthRequest()
    {
        $user = false;
        $loginButton = '';

        $this->scriptUrl = $this->getCurrentUrl();

        session_start();

        // catch the possible exceptions
        try
        {

            // Declare oauth_consumer
            $oOAuthConsumer = new OAuthConsumer($this->conf['consumerKey'], $this->conf['consumerSecret']);

            // Init GenusApis
            $oGenusApis = new GenusApis($oOAuthConsumer, $this->haVersion);

            if (($_REQUEST['action']) && $_REQUEST['action'] == "authorize")
            {
                //authorize
                // Create request token and authorize it (causes redirect).
                //$oRequestToken = $oGenusApis->retrieveRequesttoken(array("friends.get", "users.get", "albums.getByUser"));
                $oRequestToken = $oGenusApis->retrieveRequesttoken(array("users.get"));
                $_SESSION['requesttoken_' . $oRequestToken->getKey()] = serialize($oRequestToken);
                $oGenusApis->redirectToAuthorizeUrl($oRequestToken,
                        $this->scriptUrl . "?action=authorized&service=hyves"
                        . (isset($_GET['id']) ? '&id=' . $_GET['id'] : ''));
            }


//            if ($_SESSION['localtoken_' . $local_token]) echo " localtoken ";
        } catch (GeneralException $e)
        {
            throw new Exception('Ext sociallogin2t3: General Exception occured:<br>Code: ' . $e->getCode() . '<br>Message: ' . $e->getMessage());
        } catch (HyvesApiException $e)
        {
            throw new Exception('Ext sociallogin2t3: HyvesApi Exception occured:<br>Code: ' . $e->getCode() . '<br>Message: ' . $e->getMessage());
        }



        if ($this->isLoggedOutFromElse())
        {
            //echo ' isLoggedOutFromElse ';
            setcookie("hyves_oauth_token", '', time() - 100);
            setcookie("hyves_oauth_local_token", '', time() - 100);
            setcookie('service', '', time() - 100);
            unset($_COOKIE['hyves_oauth_token']);
            unset($_COOKIE['hyves_oauth_local_token']);
            unset($_COOKIE['service']);
        }

        if ($this->isLoggingOutFromHere())
        {
            //echo ' isLoggingOutFromHere ';
            setcookie("hyves_oauth_token", '', time() - 100);
            setcookie("hyves_oauth_local_token", '', time() - 100);
            setcookie('service', '', time() - 100);
            unset($_COOKIE['hyves_oauth_token']);
            unset($_COOKIE['hyves_oauth_local_token']);
            unset($_COOKIE['service']);

            $this->logoutUser();

            //$this->content[] = $loginButton;
        }
        else
        {

            if ($this->isLoggingIn())
            {
                //echo ' isLoggingIn ';
                // user accepted access

                if ($this->isLoggedInFromElse())
                {
                    //echo ' isLoggedInFromElse ';
                    // user switched pages and came back or got here directly, stilled logged in
                    //$oauth->setToken($_COOKIE['oauth_token'], $_COOKIE['oauth_token_secret']);
                }
                else
                {
                    /*       // user comes from twitter
                      $oauth->setToken($_GET['oauth_token']);
                      $token = $oauth->getAccessToken(array('oauth_verifier' => $_GET['oauth_verifier']));
                      setcookie('oauth_token', $token->oauth_token);
                      setcookie('oauth_token_secret', $token->oauth_token_secret);
                      setcookie('service', $_GET['service']);
                      $oauth->setToken($token->oauth_token, $token->oauth_token_secret);
                     */

                    // Authorized page, hyves will redirect to this page (callback).
                    $oauth_token = $_REQUEST['oauth_token'];
                    $oRequestToken = $this->getRequestTokenFromSession($oauth_token);
                    $oAccessToken = $oGenusApis->retrieveAccesstoken($oRequestToken);
                    $local_token = md5($oAccessToken->getKey());
                    $_SESSION['localtoken_' . $local_token] = serialize($oAccessToken);
                    //$overviewUrl = $this->scriptUrl . "?action=overview&local_token=" . $local_token;
                    //header("Location: " . $overviewUrl);
// Example method users.get with loggedin userid and responsefields
                    //$local_token = $_REQUEST['local_token'];
                    //$oAccessToken = $this->getAccessTokenFromSession($local_token);
                    //$oXml = $oGenusApis->doMethod("users.get", array("userid" => $oAccessToken->getUserid(), "ha_responsefields" => "profilepicture,whitespaces"), $oAccessToken);
                    $oXml = $oGenusApis->doMethod("users.get", array("userid" => $oAccessToken->getUserid()), $oAccessToken);
                    $user = $oXml->user;

                    setcookie('hyves_oauth_token', $oauth_token);
                    setcookie('hyves_oauth_local_token', $local_token);
                    setcookie('service', $_GET['service']);
                }
                //if ($this->conf['showLogoutLink'] == 1) $this->content[] = '<a href="' . $this->getCurrentUrl() . '?logintype=logout">Logout</a>';
            }
            elseif (isset($_GET['denied']))
            {
                // user denied access
                //$this->content[] = 'You must sign in first';
            }
            else
            {
                // user not logged in
                //if(!$this->isLoggedInFromElse()) $this->content[] = $loginButton;
            }
        }

        //process button:
        if ($this->isLoggingIn && $this->isLoggedInFromElse())
        {
            //if ($this->conf['showLogoutLink'] == 1) $this->content[] = '<a href="' . $this->getCurrentUrl() . '?logintype=logout">Logout</a>';
        }
        else
        {
            $buttonPath = strlen($this->conf['customButton']) > 1 ? $this->conf['customButton'] : '/' . t3lib_extmgm::siteRelPath('sociallogin2t3') . 'res/hyves-login-button.png';

            $loginButton = "<a href=\"" . $this->scriptUrl . "?action=authorize"
                    . (isset($_GET['id']) ? '&id=' . $_GET['id'] : '') . "\">"
                    . '<img src="' . $buttonPath . '" alt="sign in with hyves" />'
                    . '</a>';

            $this->content[] = '<div class="hyvesButton">' . $loginButton . '</div>';
        }
        return $user;
    }

    // example storage for requesttoken
    function getRequestTokenFromSession($oauth_token)
    {
        if (!isset($_SESSION['requesttoken_' . $oauth_token]))
        {
            header("Location: " . $this->scriptUrl . "?action=invalidsession");
        }
        return unserialize($_SESSION['requesttoken_' . $oauth_token]);
    }

    // example storage for accesstoken
    function getAccessTokenFromSession($local_token)
    {
        if (!isset($_SESSION['localtoken_' . $local_token]))
        {
            header("Location: " . $this->scriptUrl . "?action=invalidsession");
        }
        return unserialize($_SESSION['localtoken_' . $local_token]);
    }

    /**
     * Check prerequisites and exit with statement if not met
     */
    function checkServicePrerequisites()
    {
        $countFf4F = count($this->fields4Fetch);
        $countF_uF = count($this->fe_usersFields);

        if ($countFf4F != $countF_uF)
        {
            throw new Exception('Ext sociallogin2t3: Hyves constants "fields4Fetch" & "fe_usersFields" need to have the same number of elements!');
        }

        if (!isset($this->conf['consumerKey']) || $this->conf['consumerKey'] == '')
        {
            throw new Exception('Ext sociallogin2t3: Hyves consumer key is not set in constants');
        }

        if (!isset($this->conf['consumerSecret']) || $this->conf['consumerSecret'] == '')
        {
            throw new Exception('Ext sociallogin2t3: Hyves consumer secret is not set in constants');
        }
    }

}
?>