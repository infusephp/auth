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
use Infuse\Auth\Interfaces\UserInterface;
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
     * @param Request     $req
     * @param AuthManager $auth
     *
     * @return bool
     */
    public function verify(Request $req, AuthManager $auth)
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

        // hash series for matching with the db
        $seriesHash = $this->hash($this->series);

        // First, make sure all of the parameters match, except the token.
        // We match the token separately to detect if an older session is
        // being used, in which case we cowardly run away.
        $expiration = time() - $this->getExpires();
        $db = $auth->getApp()['db'];
        $query = $db->select('token,two_factor_verified')
                    ->from('PersistentSessions')
                    ->where('email', $this->email)
                    ->where('created_at', U::unixToDb($expiration), '>')
                    ->where('series', $seriesHash);

        $persistentSession = $query->one();

        if ($query->rowCount() !== 1) {
            return false;
        }

        // if there is a match, sign the user in
        $tokenHash = $this->hash($this->token);

        // Same series, but different token, meaning the user is trying
        // to use an older token. It's most likely an attack, so flush
        // all sessions.
        if (!hash_equals($persistentSession['token'], $tokenHash)) {
            $db->delete('PersistentSessions')
               ->where('email', $this->email)
               ->execute();

            return false;
        }

        // remove the token once used
        $db->delete('PersistentSessions')
           ->where('email', $this->email)
           ->where('series', $seriesHash)
           ->where('token', $tokenHash)
           ->execute();

        // mark the user as 2fa verified
        if ($persistentSession['two_factor_verified']) {
            $user->markTwoFactorVerified();
        }

        return $user;
    }

    /**
     * Persists this cookie to the database.
     *
     * @param UserInterface $user
     *
     * @throws \Exception when the model cannot be saved.
     *
     * @return PersistentSession
     */
    public function persist(UserInterface $user)
    {
        $session = new PersistentSession();
        $session->email = $this->email;
        $session->series = $this->hash($this->series);
        $session->token = $this->hash($this->token);
        $session->user_id = $user->id();
        $session->two_factor_verified = $user->isTwoFactorVerified();

        try {
            $session->save();
        } catch (\Exception $e) {
            throw new \Exception("Unable to save persistent session for user # {$user->id()}: ".$e->getMessage());
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
     * Hashes a token.
     *
     * @param string $token
     *
     * @return string hashed token
     */
    private function hash($token)
    {
        $app = Application::getDefault();
        $salt = $app['config']->get('app.salt');

        return hash_hmac('sha512', $token, $salt);
    }
}
