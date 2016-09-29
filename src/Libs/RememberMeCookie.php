<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Libs;

use Infuse\Application;
use Infuse\Auth\Models\PersistentSession;
use Infuse\Request;
use Infuse\Utility as U;
use RandomLib\Factory;
use RandomLib\Generator;

class RememberMeCookie
{
    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $userAgent;

    /**
     * @var string
     */
    private $series;

    /**
     * @var string
     */
    private $token;

    /**
     * @var object
     */
    private $random;

    /**
     * @param string       $email
     * @param string       $userAgent
     * @param string|false $series
     * @param string|false $token
     */
    public function __construct($email, $userAgent, $series = false, $token = false)
    {
        if ($series === false) {
            $series = $this->generateToken();
        }

        if ($token === false) {
            $token = $this->generateToken();
        }

        $this->email = $email;
        $this->userAgent = $userAgent;
        $this->series = $series;
        $this->token = $token;
    }

    /**
     * Gets the email address.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Gets the user agent.
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Gets the series.
     *
     * @return string
     */
    public function getSeries()
    {
        return $this->series;
    }

    /**
     * Gets the token.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Gets the expiration timestamp for the cookie.
     *
     * @param int $t
     *
     * @return int
     */
    public function getExpires($t = 0)
    {
        return $t + PersistentSession::$sessionLength;
    }

    /**
     * Decodes an encoded remember me cookie string.
     *
     * @param string $cookie
     *
     * @return self
     */
    public static function decode($cookie)
    {
        $params = (array) json_decode(base64_decode($cookie), true);

        return new self((string) array_value($params, 'user_email'),
                        (string) array_value($params, 'agent'),
                        (string) array_value($params, 'series'),
                        (string) array_value($params, 'token'));
    }

    /**
     * Encodes a remember me cookie.
     *
     * @return string
     */
    public function encode()
    {
        $json = json_encode([
            'user_email' => $this->email,
            'agent' => $this->userAgent,
            'series' => $this->series,
            'token' => $this->token,
        ]);

        return base64_encode($json);
    }

    /**
     * Checks if the cookie contains valid values.
     *
     * @return bool
     */
    public function isValid()
    {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (empty($this->userAgent)) {
            return false;
        }

        if (empty($this->series)) {
            return false;
        }

        if (empty($this->token)) {
            return false;
        }

        return true;
    }

    /**
     * Verifies the cookie against an incoming request.
     *
     * @param Request $req
     * @param Auth    $auth
     *
     * @return bool
     */
    public function verify(Request $req, Auth $auth)
    {
        if (!$this->isValid()) {
            return false;
        }

        // verify the user agent matches the one in the request
        if ($this->userAgent != $req->agent()) {
            return false;
        }

        // look up the user with a matching email address
        $userClass = $auth->getUserClass();
        $user = $userClass::where('email', $this->email)
                          ->first();

        if (!$user) {
            return false;
        }

        // encrypt series for matching with the db
        $seriesEnc = $this->encrypt($this->series);

        // First, make sure all of the parameters match, except the token.
        // We match the token separately to detect if an older session is
        // being used, in which case we cowardly run away.
        $expiration = time() - $this->getExpires();
        $db = $auth->getApp()['db'];
        $query = $db->select('token')
                    ->from('PersistentSessions')
                    ->where('email', $this->email)
                    ->where('created_at', U::unixToDb($expiration), '>')
                    ->where('series', $seriesEnc);

        $tokenDB = $query->scalar();

        if ($query->rowCount() !== 1) {
            return false;
        }

        // if there is a match, sign the user in
        $tokenEnc = $this->encrypt($this->token);

        // Same series, but different token, meaning the user is trying
        // to use an older token. It's most likely an attack, so flush
        // all sessions.
        if ($tokenDB != $tokenEnc) {
            $db->delete('PersistentSessions')
               ->where('email', $this->email)
               ->execute();

            return false;
        }

        // remove the token once used
        $db->delete('PersistentSessions')
           ->where('email', $this->email)
           ->where('series', $seriesEnc)
           ->where('token', $tokenEnc)
           ->execute();

        return $user;
    }

    public function persist($userId)
    {
        $session = new PersistentSession();
        $session->email = $this->email;
        $session->series = $this->encrypt($this->series);
        $session->token = $this->encrypt($this->token);
        $session->user_id = $userId;

        try {
            $session->save();
        } catch (\Exception $e) {
            throw new \Exception("Unable to save persistent session for user # $userId: ".$e->getMessage());
        }

        return $session;
    }

    /**
     * Generates a random token.
     *
     * @param int $len
     *
     * @return string
     */
    private function generateToken($len = 32)
    {
        if (!$this->random) {
            $factory = new Factory();
            $this->random = $factory->getMediumStrengthGenerator();
        }

        return $this->random->generateString($len, Generator::CHAR_ALNUM);
    }

    /**
     * Encrypts a token.
     *
     * @param string $token
     *
     * @return string encrypted token
     */
    private function encrypt($token)
    {
        $app = Application::getDefault();
        $salt = $app['config']->get('app.salt');

        return U::encryptPassword($token, $salt);
    }
}
