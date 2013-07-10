<?php
/**
 * Shopware 4.0
 * Copyright © 2013 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

use Shopware\Components\Password\Encoder\PasswordEncoderInterface;

/**
 * Password interface for md5 hashed with salt first
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2013, shopware AG (http://www.shopware.de)
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
