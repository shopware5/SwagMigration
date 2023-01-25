<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\DbServices\Import;

use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;

class TranslationImporter
{
    /**
     * @var PDOConnection
     */
    private $db;

    public function __construct(PDOConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param string $objectType
     * @param string $objectKey
     * @param string $objectLanguage
     *
     * @return bool|int
     */
    public function import($objectType, $objectKey, $objectLanguage, array $objectData)
    {
        if (empty($objectType) || empty($objectKey) || empty($objectLanguage)) {
            return false;
        }

        if (empty($objectData)) {
            return $this->deleteTranslation($objectType, $objectKey, $objectLanguage);
        }

        $objectData = $this->prepareTranslationData($objectData, $objectType, $objectLanguage);

        if (empty($objectData)) {
            return $this->deleteTranslation($objectType, $objectKey, $objectLanguage);
        }

        $objectType = $this->db->quote((string) $objectType);
        $objectKey = $this->db->quote((string) $objectKey);
        $objectLanguage = $this->db->quote((string) $objectLanguage);
        $objectData = $this->db->quote(\serialize($objectData));

        $id = $this->findExistingEntry($objectType, $objectKey, $objectLanguage);
        $objectDataId = $this->createOrUpdate($id, $objectType, $objectData, $objectKey, $objectLanguage);

        return $objectDataId;
    }

    /**
     * @param string $objectType
     * @param string $objectKey
     * @param string $objectLanguage
     *
     * @return bool
     */
    private function deleteTranslation($objectType, $objectKey, $objectLanguage)
    {
        if (empty($objectType)) {
            return false;
        }
        $objectType = $this->db->quote($objectType);

        $sql = 'DELETE FROM s_core_translations
                WHERE objecttype IN (' . $objectType . ')';
        if (!empty($objectKey)) {
            $objectKey = $this->db->quote($objectKey);
            $sql .= ' AND objectkey IN (' . $objectKey . ')';
        }

        if (!empty($objectLanguage)) {
            $sql .= ' AND objectlanguage = ' . $this->db->quote($objectLanguage);
        }

        return (bool) $this->db->query($sql);
    }

    /**
     * @param string $objectType
     * @param string $objectLanguage
     *
     * @return array
     */
    private function prepareTranslationData(array $objectData, $objectType, $objectLanguage)
    {
        $map = ['txtzusatztxt' => 'additionaltext'];
        if ($objectType === 'article') {
            $map['txtArtikel'] = 'name';
            $map['txtshortdescription'] = 'description';
            $map['txtlangbeschreibung'] = 'description_long';
            $map['txtkeywords'] = 'keywords';
        }

        $data = [];
        foreach ($map as $key => $name) {
            if (!empty($objectData[$key])) {
                $data[$key] = $objectData[$key];
            } elseif (!empty($objectData[$name . '_' . $objectLanguage])) {
                $data[$key] = $objectData[$name . '_' . $objectLanguage];
            } elseif (!empty($objectData[$name])) {
                $data[$key] = $objectData[$name];
            }
        }
        unset($map);

        for ($i = 1; $i <= 20; ++$i) {
            if (!empty($objectData["attr{$i}_$objectLanguage"])) {
                $data["attr$i"] = $objectData["attr{$i}_$objectLanguage"];
            } elseif (!empty($objectData["attr$i"])) {
                $data["attr$i"] = $objectData["attr$i"];
            }
        }

        if (isset($data['txtArtikel'])) {
            $data['txtArtikel'] = $this->toString($data['txtArtikel']);
        }

        if (isset($data['txtzusatztxt'])) {
            $data['txtzusatztxt'] = $this->toString($data['txtzusatztxt']);
        }

        return $data;
    }

    /**
     * @param string $objectType
     * @param string $objectKey
     * @param string $objectLanguage
     *
     * @return int
     */
    private function findExistingEntry($objectType, $objectKey, $objectLanguage)
    {
        $sql = 'SELECT id
                FROM s_core_translations
                WHERE objecttype = ?
                  AND objectkey = ?
                  AND objectlanguage = ?';

        return (int) $this->db->fetchOne($sql, [$objectType, $objectKey, $objectLanguage]);
    }

    /**
     * @param int    $id
     * @param string $objectType
     * @param string $objectData
     * @param string $objectKey
     * @param string $objectLanguage
     *
     * @return bool|int
     */
    private function createOrUpdate($id, $objectType, $objectData, $objectKey, $objectLanguage)
    {
        if (empty($id)) {
            $sql = 'INSERT INTO s_core_translations (objecttype, objectdata, objectkey, objectlanguage)
                    VALUES (?,?,?,?)';
            $result = $this->db->query($sql, [$objectType, $objectData, $objectKey, $objectLanguage]);

            if (empty($result)) {
                return false;
            }

            return (int) $this->db->lastInsertId();
        }
        $sql = 'UPDATE s_core_translations
                    SET	objectdata = ?
                    WHERE id = ?';
        $result = $this->db->query($sql, [$objectData, $id]);
        if (empty($result)) {
            return false;
        }

        return $id;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function toString($value)
    {
        $value = \html_entity_decode($value);
        $value = \preg_replace('!<[^>]*?>!', ' ', $value);
        $value = \str_replace(\chr(0xA0), ' ', $value);
        $value = \preg_replace('/\s\s+/', ' ', $value);
        $value = \htmlspecialchars($value);
        $value = \trim($value);

        return $value;
    }
}
