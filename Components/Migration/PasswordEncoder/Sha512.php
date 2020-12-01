<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\PasswordEncoder;

use Shopware\Components\Password\Encoder\PasswordEncoderInterface;

/**
 * Password interface for md5 hashed with salt first
 */
class Sha512 implements PasswordEncoderInterface
{
    /**
     * @var array
     */
    protected $options = [
        'iterations' => 1000,
        'salt_len' => 32,
    ];

    /**
     * @return string
     */
    public function getName()
    {
        return 'sha512';
    }

    /**
     * @param string $password
     * @param string $hash
     *
     * @return bool
     */
    public function isPasswordValid($password, $hash)
    {
        if (\strpos($hash, ':') === false) {
            return $hash == \md5($password);
        }

        list($sha512, $salt) = \explode(':', $hash);

        return \hash('sha512', $password . $salt) == $sha512;
    }

    /**
     * @param string $password
     *
     * @return string
     */
    public function encodePassword($password)
    {
        $iterations = $this->options['iterations'];
        $salt = $this->getSalt();

        return $this->generateInternal($password, $salt, $iterations);
    }

    /**
     * @param string $hash
     *
     * @return bool
     */
    public function isReencodeNeeded($hash)
    {
        return false;
    }

    /**
     * Generate a salt using the best number generator available
     *
     * @return string
     */
    public function getSalt()
    {
        $required_salt_len = $this->options['salt_len'];

        $buffer = '';
        $raw_length = (int) ($required_salt_len * 3 / 4 + 1);
        $buffer_valid = false;
        if (\function_exists('mcrypt_create_iv') && !\defined('PHALANGER')) {
            $buffer = mcrypt_create_iv($raw_length, MCRYPT_DEV_URANDOM);
            if ($buffer) {
                $buffer_valid = true;
            }
        }
        if (!$buffer_valid && \function_exists('openssl_random_pseudo_bytes')) {
            $buffer = \openssl_random_pseudo_bytes($raw_length);
            if ($buffer) {
                $buffer_valid = true;
            }
        }
        if (!$buffer_valid && \is_readable('/dev/urandom')) {
            $f = \fopen('/dev/urandom', 'r');
            $read = \strlen($buffer);
            while ($read < $raw_length) {
                $buffer .= \fread($f, $raw_length - $read);
                $read = \strlen($buffer);
            }
            \fclose($f);
            if ($read >= $raw_length) {
                $buffer_valid = true;
            }
        }
        if (!$buffer_valid || \strlen($buffer) < $raw_length) {
            $bl = \strlen($buffer);
            for ($i = 0; $i < $raw_length; ++$i) {
                if ($i < $bl) {
                    $buffer[$i] = $buffer[$i] ^ \chr(\mt_rand(0, 255));
                } else {
                    $buffer .= \chr(\mt_rand(0, 255));
                }
            }
        }
        $salt = \str_replace('+', '.', \base64_encode($buffer));

        return \substr($salt, 0, $required_salt_len);
    }

    /**
     * @param string $password
     * @param string $salt
     * @param int    $iterations
     *
     * @return string
     */
    protected function generateInternal($password, $salt, $iterations)
    {
        $hash = '';
        for ($i = 0; $i <= $iterations; ++$i) {
            $hash = \hash('sha512', $hash . $password . $salt);
        }

        return $iterations . ':' . $salt . ':' . $hash;
    }
}
