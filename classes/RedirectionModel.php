<?php
/**
 * NOVIUS.
 *
 * @copyright  2018 Novius
 * @see http://www.novius.com
 */
class RedirectionModel extends ObjectModel
{
    protected static $redirection_types = [
        [
            'type' => '301',
            'name' => '301 - Moved Permanently',
            'header' => 'HTTP/1.1 301 Moved Permanently',
        ],
        [
            'type' => '302',
            'name' => '302 - Moved Temporarily',
            'header' => 'HTTP/1.1 302 Found',
        ],
    ];

    public $id_redirection;

    public $old_url;

    public $new_url;

    public $redirection_type;

    public $date_add;

    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'seo_redirection',
        'primary' => 'id_redirection',
        'fields' => [
            'old_url' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'required' => true],
            'new_url' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'required' => true],
            'redirection_type' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    public static function dropTables()
    {
        $sql = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'seo_redirection`';

        return Db::getInstance()->execute($sql);
    }

    public static function createRedirectionTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'seo_redirection`(
			`id_redirection` int(10) unsigned NOT NULL auto_increment,
			`old_url` VARCHAR(255) NOT NULL,
			`new_url` VARCHAR(255) NOT NULL,
			`redirection_type` enum(\'301\', \'302\') NOT NULL DEFAULT \'301\',
			`date_add` DATETIME NOT NULL,
			`date_upd` DATETIME NOT NULL,
			PRIMARY KEY (`id_redirection`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';

        return Db::getInstance()->execute($sql);
    }

    public static function getListRedirections($limit, $offset)
    {
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'seo_redirection` WHERE 1 ORDER BY `id_redirection` ASC LIMIT '.(int) $limit.' OFFSET '.(int) $offset;

        return Db::getInstance()->executeS($sql);
    }

    public static function getTotalRedirections()
    {
        $sql = 'SELECT COUNT(*) FROM `'._DB_PREFIX_.'seo_redirection` WHERE 1';

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    public static function findRedirectionByOldUrl($old_url, $like_search = false)
    {
        $suffix_like = '';
        if ($like_search) {
            $suffix_like = '%';
        }

        $sql = 'SELECT new_url, redirection_type, new_url    
			FROM `'._DB_PREFIX_.'seo_redirection` sr
			WHERE `old_url` LIKE \''.pSQL($old_url).$suffix_like.'\'';

        return Db::getInstance()->getRow($sql);
    }

    public static function getRedirectionsTypes()
    {
        return static::$redirection_types;
    }

    public static function isValidRedirectionType($type)
    {
        $b_valid = false;
        if (is_array(static::$redirection_types)) {
            foreach (static::$redirection_types as $redirection_type) {
                if (! empty($redirection_type['type']) && (int) $redirection_type['type'] === (int) $type) {
                    $b_valid = true;
                    break;
                }
            }
        }

        return $b_valid;
    }

    public static function getRedirectionTypeInfos($type)
    {
        $infos = [];
        if (is_array(static::$redirection_types)) {
            foreach (static::$redirection_types as $redirection_type) {
                if (! empty($redirection_type['type']) && (int) $redirection_type['type'] === (int) $type) {
                    $infos = $redirection_type;
                    break;
                }
            }
        }

        return $infos;
    }
}
