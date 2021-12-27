<?php

/**
 * 用户组件
 *
 * @Author 陆之岇(Kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team
 */
class Nabo_User
{
    /**
     * @var array
     */
    public $identity = [];

    /**
     * @var int
     */
    public $uid;

    /**
     * @var mixed
     */
    public $data;

    /**
     * @return string
     */
    public function uin()
    {
        return $this->identity['uin'];
    }

    /**
     * @return string
     */
    public function token()
    {
        return $this->identity['token'];
    }

    /**
     * @param $identity
     * @throws Exception
     */
    public function identity($identity)
    {
        $this->identity['name'] = (string)$identity['name'];
        $this->identity['token'] = (string)$identity['token'];
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function register()
    {
        // login
        $user = wp_authenticate(
            $this->identity['name'],
            $this->identity['token']
        );

        // check
        if (is_wp_error($this->data = $user)) {
            // sleep
            $this->sleep();
            throw new Crash(
                '用户不存在', 101
            );
        }

        // current
        $this->uid = (int)$user->ID;
        wp_set_current_user($user->ID);

        return $user;
    }

    /**
     * @param $data
     * @return array
     * @throws Exception
     */
    public function login($data)
    {
        return $this->response(
            $this->register()
        );
    }

    /**
     * @param $credential
     * @return array
     * @throws Exception
     */
    public function challenge($credential)
    {
        return $this->login($credential);
    }

    /**
     * @param int $seconds
     */
    public function sleep($seconds = 6)
    {
        sleep($seconds);
    }

    /**
     * @param string $role
     * @return bool
     */
    public function pairing($role = 'administrator')
    {
        return empty($this->data) ? false :
            boolval($this->data->caps[$role]);
    }

    /**
     * @param $user
     * @param $token
     * @return array
     */
    public function response($user, $token = false)
    {
        return array(
            'user' => Nabo_Format::user(
                $user, $token
            )
        );
    }
}