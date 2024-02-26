<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 25.08.2018
 * Time: 12:30
 */

namespace Imy\Core;

class Migrator
{

    static function migrate($project,$root = CORE_ROOT)
    {
        $migration_dir = $root . $project . DS . 'migrations' . DIRECTORY_SEPARATOR;

        if (!is_dir($migration_dir)) {
            $error = "\n" . 'There is no migration folder in ' . $migration_dir . "\n\n";
            $error .= "\n";
            die($error);
        }

        $init_migration_file = $migration_dir . 'init.sql';
        if (!file_exists($init_migration_file) && $project == 'core') {
            $error = "\n" . 'There is no initialise migration ' . $init_migration_file . "\n\n";
            $error .= "\n";
            die($error);
        }

        $db = DB::getInstance();

        try {
            $last_migration = M('imy_migration')->get()->orderBy('date', 'DESC')->orderBy('num', 'DESC')->fetch();
            if(!isset($last_migration->name)) {
                $db->exec('ALTER TABLE imy_migration ADD name VARCHAR(512) NOT NULL DEFAULT "" AFTER num');
            }
        } catch (\Exception $e) {
            $sql = file_get_contents($init_migration_file);
            $db->exec($sql);
        }

        $loaded = M('imy_migration')->get()->fetchAll();
        $arrLoaded = [];
        foreach($loaded as $row) {
            $arrLoaded[] = str_replace('-','',$row->date) . ($row->num ? '-' . ($row->num < 10 ? '0' : '') . $row->num : '') . ($row->name ? '_' . $row->name : '') . '.sql';
        }

        $files = scandir($migration_dir);
        $to_migrate = [];
        foreach ($files as $file) {

            if(in_array($file,$arrLoaded) || strpos($file,'.sql') === false)
                continue;

            console('Will be load ' . $file);
            $to_migrate[] = $file;
        }


        if (!empty($to_migrate)) {
            foreach ($to_migrate as $file) {
                $sql = file_get_contents($migration_dir . $file);
                console('Load from ' . $migration_dir . $file);
                try {
                    if (!empty($sql)) {
                        $db->exec($sql);
                    }
                } catch (Exception $e) {
                    $error = "\n" . 'Error migration ' . $file . "\n\n";
                    $error .= "\n";
                    die($error);
                }

                $file = explode('_', $file);
                $datePart = array_shift($file);
                $datePart = explode('-',$datePart);
                $name = implode('_',$file);

                $migration = M('imy_migration')->factory();
                $migration->setValues(
                    [
                        'date'  => date('Y-m-d', strtotime($datePart[0])),
                        'num'   => (int)$datePart[1],
                        'name' => $name,
                        'cdate' => NOW
                    ]
                );
                $migration->save();
            }
        }
    }
}
