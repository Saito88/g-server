<?php
/**
 * Created by PhpStorm.
 * User: Saito88
 * Date: 14.09.2017
 * Time: 15:04
 */

namespace gserver\repositorities;


abstract class Table
{
    /**
     * @var Table|null
     */
    private static $Instance = NULL;

    /**
     * @var Db|null
     */
    private $Db = NULL;

    /**
     * Table constructor.
     */
    private function __construct() {}

    /**
     * Singleton pattern don't allow clone
     */
    private function __clone() {}

    /**
     * @return mixed
     */
    abstract public static function getInstance();

    /**
     * Returns the latest Dataset
     *
     * @return array
     */
    public function getDataset(): array {

        $Reflection = new \ReflectionClass($this);

        foreach ($Reflection->getProperties() as $obj) {
            $vars[$obj->name] = '';
        }

        // Remove Instance
        array_shift($vars);
        // Remove Db
        array_shift($vars);

        foreach ($vars as $key => &$value) {
            $function = 'get' . ucfirst($key);
            $value = $this->$function();
        }

        return $vars;

    }

    /**
     * Set all class properties from a Db result
     *
     * @param array $params
     */
    public function setDataset(array $params = []): void {

        if ($this->Db === NULL) {
            $this->Db = Gserver()->Db();
        }

        $Reflection = new \ReflectionClass($this);
        $table = $Reflection->getShortName();

        if (empty($params)) {
            $row = $this->Db->fetchRow("SELECT * FROM " . $table);
        } else {

            if (count($params) !== 2) {
                // Failure
            }
            else {

                $key = array_shift($params);
                $value = array_shift($params);

                if (empty($value)) {
                    $value = "''";
                }

                if(!empty($value) && !is_numeric($value)) {
                    $value = "'" . $value . "'";
                }

                $row = $this->Db->fetchRow("SELECT * FROM " . $table . " WHERE " . $key . " = " . $value);

            }

        }

        if (!empty($row)) {

            foreach ($Reflection->getProperties() as $obj) {
                $vars[] = $obj->name;
            }

            $vars2 = $vars;

            // Remove Instance
            array_shift($vars);
            // Remove Db
            array_shift($vars);

            foreach ($row as $key => $value) {
                $function = 'set' . ucfirst(array_shift($vars));
                $this->$function($value);
            }

        } else {
            $this->setId(0);
        }

    }

    public function getAll(array $params = []): array {

        if ($this->Db === NULL) {
            $this->Db = Gserver()->Db();
        }

        $where = '';

        foreach ($params as $key => $value) {

            if (empty($value)) {
                $value = "''";
            }

            if (!empty($value) && !is_numeric($value)) {
                $value = "'" . $value . "'";
            }

            $where .= $key . ' = ' . $value . ' AND ';
        }

        $where = substr($where,0,-5);

        $Reflection = new \ReflectionClass($this);
        $table = $Reflection->getShortName();

        return $this->Db->fetchAll("SELECT * FROM " . $table . " WHERE " . $where);

    }
}