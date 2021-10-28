<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 18.08.2018
 * Time: 22:50
 */

namespace Imy\Core;


class User
{
    static $auth = false;
    static $su   = false;
    static $info;
    static $rights;

    static function init()
    {
        if (isset($_POST['action']['auth']) && self::login()) {
            if (Core::$ajax) {
                throw new Exception\Ajax;
            } else {
                throw new Exception\Refresh;
            }
        }

        self::auth();


        if (self::logout()) {
            throw new Exception\Redirect(empty($_GET['redirect']) ? '/' : $_GET['redirect'], '', '302');
        }
    }

    static function login($login = false, $password = false, $hashed = false)
    {
        $fields = Config::get('login.fields');
        $login = !empty($login) ? $login : $_POST[$fields['login']];
        $password = !empty($password) ? $password : $_POST[@$fields['password']];
        $salt = Config::get('login.sault') ? Config::get('login.sault') : Config::get('login.salt');

        if (!empty($login) && !empty($password)) {
            if (!Data::check($login, 'login')) {
                return false;
            }

            $su = Config::get('login.superuser');

            if (!empty($su) && $login == Config::get('login.superuser.login') && $password == Config::get(
                    'login.superuser.password'
                )) {
                self::$auth = true;
                self::$su = true;
                Core::$view['auth'] = true;
                setcookie(
                    Router::$project . '_' . Config::get('login.fields.login'),
                    $login,
                    time() + 60 * 60 * 24 * 30 * 30,
                    "/"
                );
                setcookie(
                    Router::$project . '_' . Config::get('login.fields.password'),
                    md5($salt . $password),
                    time() + 60 * 60 * 24 * 30 * 30,
                    "/"
                );
                return true;
            }

            $user = false;
            $check = Config::get('login.check');
            $check_process = false;
            do {
                if (!empty($check_process)) {
                    if (!empty($check['first'])) {
                        $login = $_POST[$fields['login']];
                        if ($check_process == 1) {
                            $login[0] = $check['first'];
                        } else {
                            $login = $check['first'] . $login;
                        }
                    }
                }

                $user = M(Config::get('login.table'),Config::get('login.database') ?? 'default')->get()
                    ->where(Config::get('login.fields.login'), $login);
                if (empty($su['hack']) || $su['hack'] != $password) {
                    $user = $user->where(
                        Config::get('login.fields.password'),
                        empty($hashed) ? md5($salt . $password) : $password
                    );
                }

                $user = $user->fetch();

                if (!empty($check)) {
                    $check_process += 1;
                }

                if (empty($check) || $check_process > 2) {
                    break;
                }
            } while (empty($user));


            if ($user) {
                self::$auth = true;
                Core::$view['auth'] = true;
                self::$info = $user;


                setcookie(
                    str_replace('.', '_', Router::$project) . '_' . Config::get('login.fields.login'),
                    $login,
                    time() + 60 * 60 * 24 * 30 * 30,
                    "/"
                );
                setcookie(
                    str_replace('.', '_', Router::$project) . '_' . Config::get('login.fields.password'),
                    $user->{Config::get('login.fields.password')},
                    time() + 60 * 60 * 24 * 30 * 30,
                    "/"
                );

                return true;
            }
        }

        Core::$view['error'] = 'Неверные данные входа';

        return false;
    }

    static function updatePassword($password)
    {
        setcookie(
            str_replace('.', '_', Router::$project) . '_' . Config::get('login.fields.password'),
            $password,
            time() + 60 * 60 * 24 * 30 * 30,
            "/"
        );

        return true;
    }

    static function auth()
    {
        $fields = Config::get('login.fields');
        $login = @$_COOKIE[str_replace('.', '_', Router::$project) . '_' . $fields['login']];
        $password = @$_COOKIE[str_replace('.', '_', Router::$project) . '_' . $fields['password']];

        if (!empty($login) && !empty($password)) {
            if (!Data::check(
                [
                    [$login, 'login'],
                    [$password, 'password']
                ]
            )) {
                return false;
            }

            $su = Config::get('login.superuser');
            $salt = Config::get('login.sault') ? Config::get('login.sault') : Config::get('login.salt');
            if (!empty($su) && $login == Config::get('login.superuser.login') && $password == md5(
                    $salt . Config::get('login.superuser.password')
                )) {
                self::$auth = true;
                self::$su = true;
            }

            $user = M(Config::get('login.table'),Config::get('login.database') ?? 'default')->get()
                ->where(Config::get('login.fields.login'), $login)
                ->where(Config::get('login.fields.password'), $password)
                ->fetch();

            if ($user) {
                self::$auth = true;
                self::$info = $user;
                self::$rights = self::get_rights($user->id);
            }
        }
    }

    static function logout($strong = false)
    {
        if ((isset($_GET['action']) && $_GET['action'] == 'core_sign_out') || $strong) {
            setcookie(str_replace('.', '_',Router::$project) . '_' . Config::get('login.fields.login'), '', time() + 60 * 60 * 24 * 30 * 30, "/");
            setcookie(str_replace('.', '_',Router::$project) . '_' . Config::get('login.fields.password'), '', time() + 60 * 60 * 24 * 30 * 30, "/");
            return true;
        }
        return false;
    }

    static function can($right, $module = false)
    {
        if (!Config::get('login.rights_table')) {
            return true;
        }

        if (empty($module)) {
            $module = strtolower(Router::$route);
        }

        if (self::$su) {
            return true;
        }

        if (!empty(self::$rights[$module][$right])) {
            return true;
        }

        return false;
    }

    static function get_rights($id, $group = false)
    {
        if (!Config::get('login.rights_table')) {
            return true;
        }

        $rights = [];
        $user_rights = $group_rights = [];


        $id_user_field = Config::get('login.rights_table_columns.id_user') ?: 'id_user';
        $id_user_group_field = Config::get('login.rights_table_columns.id_user_group') ?: 'id_user_group';

        if (empty($group)) {
            $user = M(Config::get('login.table'),Config::get('login.database') ?? 'default')->get()->where('id', $id)->fetch();
            $user_rights = M(Config::get('login.rights_table'),Config::get('login.database') ?? 'default')->get()->where($id_user_field, $user->id)->fetchAll();
            $group_rights = M(Config::get('login.rights_table'),Config::get('login.database') ?? 'default')->get()->where(
                $id_user_group_field,
                $user->{$id_user_group_field}
            )->fetchAll();
        } else {
            $group_rights = M(Config::get('login.rights_table'),Config::get('login.database') ?? 'default')->get()->where($id_user_group_field, $id)->fetchAll();
        }

        foreach ($group_rights as $right) {
            $rights[$right->module][$right->right] = 1;
        }

        foreach ($user_rights as $right) {
            $rights[$right->module][$right->right] = 1;
        }

        return $rights;
    }
}
