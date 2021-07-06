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

    static function migrate($project)
    {
        $migration_dir = CORE_ROOT . $project . DS . 'migrations' . DIRECTORY_SEPARATOR;

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
        } catch (\Exception $e) {
            $sql = file_get_contents($init_migration_file);
            $db->exec($sql);
        }

        $files = scandir($migration_dir);
        $to_migrate = [];
        foreach ($files as $file) {
            $name = $file;
            $file = explode('-', $file);

            if (is_numeric($file[0])) {
                if (!empty($last_migration)) {
                    $date = str_replace('-', '', $last_migration->date);
                    if ($file[0] < $date || ($file[0] == $date && (int)$file[1] <= $last_migration->num)) {
                        continue;
                    }
                }

                console('Will be load ' . $name);


                $to_migrate[] = $name;
            }
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

                $file = explode('-', $file);

                $migration = M('imy_migration')->factory();
                $migration->setValues(
                    [
                        'date'  => date('Y-m-d', strtotime($file[0])),
                        'num'   => (int)$file[1],
                        'cdate' => NOW
                    ]
                );
                $migration->save();
            }
        }
    }
}
