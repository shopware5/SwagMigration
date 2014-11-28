<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Shopware\Components\Password\Encoder\PasswordEncoderInterface;

/**
 * Password interface for md5 hashed with salt first
 */
class Shopware_Components_Migration_PasswordEncoder_Md5Reversed implements PasswordEncoderInterface
{

    /**
     * @return string
     */
    public function getName()
    {
        return 'md5reversed';
    }

    /**
     * @param  string $password
     * @param  string $hash
     * @return bool
     */
    public function isPasswordValid($password, $hash)
    {
        if (strpos($hash, ':') === false) {
            return $hash == md5($password);
        }
        list($md5, $salt) = explode(':', $hash);

        return $md5 == md5($salt . $password);
    }

    /**
     * @param  string $password
     * @return string
     */
    public function encodePassword($password)
    {
        return md5($password);
    }

    /**
     * @param  string $hash
     * @return bool
     */
    public function isReencodeNeeded($hash)
    {
        return false;
    }
}
