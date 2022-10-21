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
class Md5Reversed implements PasswordEncoderInterface
{
    public const HASH_VERSION_MD5 = 0;
    public const HASH_VERSION_SHA256 = 1;
    public const HASH_VERSION_SHA512 = 2;

    /**
     * Encryption method bcrypt
     */
    public const HASH_VERSION_LATEST = 3;

    /**
     * @return string
     */
    public function getName()
    {
        return 'md5reversed';
    }

    /**
     * @param string $password
     * @param string $hash
     *
     * @return bool
     */
    public function isPasswordValid($password, $hash)
    {
        if ($this->validateHash($password, $hash)) {
            return true;
        }

        if (strpos($hash, ':') === false) {
            return $hash == md5($password);
        }
        list($md5, $salt) = \explode(':', $hash);

        return $md5 == \md5($salt . $password);
    }

    /**
     * @param string $password
     *
     * @return string
     */
    public function encodePassword($password)
    {
        return \md5($password);
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

    private function validateHash($password, $hash)
    {
        return $this->validateHashByVersion($password, $hash, self::HASH_VERSION_LATEST)
            || $this->validateHashByVersion($password, $hash, self::HASH_VERSION_SHA512)
            || $this->validateHashByVersion($password, $hash, self::HASH_VERSION_SHA256)
            || $this->validateHashByVersion($password, $hash, self::HASH_VERSION_MD5);
    }

    /**
     * Validate hash by specified version
     *
     * @param string $password
     * @param string $hash
     * @param int    $version
     *
     * @return bool
     */
    private function validateHashByVersion($password, $hash, $version = self::HASH_VERSION_MD5)
    {
        if ($version == self::HASH_VERSION_LATEST) {
            return password_verify($password, $hash);
        }
        // look for salt
        $hashArr = explode(':', $hash, 2);
        if (\count($hashArr) === 1) {
            return hash_equals($this->hash($password, $version), $hash);
        }
        list($hash, $salt) = $hashArr;

        return hash_equals($this->hash($salt . $password, $version), $hash);
    }

    private function hash($data, $version = self::HASH_VERSION_MD5)
    {
        if ($version === self::HASH_VERSION_LATEST) {
            return password_hash($data, \PASSWORD_DEFAULT);
        } elseif ($version == self::HASH_VERSION_SHA256) {
            return hash('sha256', $data);
        } elseif ($version == self::HASH_VERSION_SHA512) {
            return hash('sha512', $data);
        }

        return md5($data);
    }
}
