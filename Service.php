<?php

/**
 * 南博 KAT-RPC 接口
 *
 * @Author 陆之岇(Kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team
 */
class Nabo_Service
{
    /**
     * @var Kat_Server
     */
    protected $server;

    /**
     * @var Nabo_User
     */
    protected $liteuser;

    /**
     * @throws Exception
     */
    public function launch()
    {
        // data
        $request = file_get_contents(
            'php://input'
        );

        // check data
        if (empty($request)) {
            die('Kat server accepts POST requests only');
        }

        // report
        error_reporting(E_ERROR);

        include_once 'Kat/Kat.php';
        include_once 'Kat/Plus.php';
        include_once 'Kat/Server.php';

        include_once 'User.php';
        include_once 'Format.php';

        // user
        $this->liteuser = new Nabo_User();

        // server
        $this->server = new Kat_Server();

        // register
        $this->server->register($this);

        // launch
        $this->server->receive(
            $this, $request
        );
    }

    /**
     * @return void
     */
    public function hooker_start()
    {
        // stream
    }

    /**
     * @param $kat
     * @param $header
     */
    public function hooker_end($kat, $header)
    {
        // kat
        foreach ($header as $key => $val) {
            header("$key: $val");
        }
        header('Date: ' . date('r'));
        header('Connection: close');
        header('Content-Length: ' . strlen($kat));
        exit($kat);
    }

    /**
     * @param $kat
     * @throws Exception
     */
    public function hooker_accept(&$kat)
    {
        // cert
        $cert = $kat['cert'];

        // digest
        $digest = md5(
            $kat['request']
        );

        // check
        if ($cert['digest'] != $digest) {
            throw new Crash(
                '非法请求', 102
            );
        }

        // cert
        $auth = $kat['auth'];

        // identity
        $this->liteuser->identity($auth);

        // check
        switch ($kat['method']) {
            case 'kat_user_login':
            case 'kat_user_challenge':
                break;
            default:
            {
                // challenge
                $this->liteuser->register();
            }
        }
    }

    /**
     * @param $kat
     * @param $callback
     */
    public function hooker_challenge($kat, &$callback)
    {
        $callback['cert'] = [
            'digest' => md5($callback['response'])
        ];
    }

    /**
     * 验证权限
     *
     * @param string $level
     * @throws Exception
     */
    public function check_access($level = 'contributor')
    {
        if (!$this->liteuser->pairing()) {
            throw new Crash(
                '用户权限不足', 101
            );
        }
    }

    /**
     * 版本
     *
     * @param $data
     * @return array
     */
    public function method_kat_version($data)
    {
        return [
            'engine' => 'wordpress',
            'versionCode' => 25,
            'versionName' => '4.1'
        ];
    }

    /**
     * 登录
     *
     * @access public
     * @param $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_user_login($data)
    {
        return $this->liteuser->login($data);
    }

    /**
     * 登录
     *
     * @access public
     * @param $credential
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_user_challenge($credential)
    {
        return $this->liteuser->challenge($credential);
    }

    /**
     * 用户
     *
     * @access public
     * @param $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_user_pull($data)
    {
        $this->check_access();

        // touch
        $touch = (int)$data['touch'];

        // callback
        $callback = array(
            'region' => 'sync'
        );

        // user
        $user = $this->liteuser->data;

        // merge
        if ($touch) {
            $callback = array_merge($callback,
                $this->liteuser->response($user)
            );
        }

        return $callback;
    }

    /**
     * @param $data
     * @return array
     * @throws Exception
     */
    public function metcip_kat_stat_pull($data)
    {
        $this->check_access();

        return [
            'creative' => Nabo_Format::create_words(
                $this->liteuser->uid
            )
        ];
    }

    /**
     * @param $data
     * @return KatAry|Exception
     * @throws Exception
     */
    public function metcip_kat_note_drag($data)
    {
        // check type
        switch ($type = $data['type']) {
            case 'post':
                $this->check_access();
                break;
            case 'page':
                $this->check_access('editor');
                break;
            default:
                return new Crash('异常请求');
        }

        // check
        $target = get_post_type_object($type);
        if (!current_user_can($target->cap->edit_posts)) {
            return new Crash(
                '对不起，您不允许编辑此帖子类型的帖子', 401
            );
        }

        // query
        $query = array(
            'post_type' => $target->name
        );

        // meta
        if (is_numeric($data['meta'])) {
            $query['category'] = $data['meta'];
        }

        // status
        switch ($status = $data['status'] ?: 'allow') {
            case 'allow':
                break;
            case 'draft':
                $query['post_status'] = 'draft';
                break;
            default:
                $query['post_status'] = Nabo_Format::note_status($status);
        }

        // search
        if (isset($data['search'])) {
            $query['s'] = $data['search'];
        }

        // paging
        Nabo_Format::paging(
            $data, $offset, $length
        );
        $query['offset'] = $offset;
        $query['numberposts'] = $length;

        return Nabo_Format::notes(
            wp_get_recent_posts($query)
        );
    }

    /**
     * @param $data
     * @return KatAny|Exception
     * @throws Exception
     */
    public function metcip_kat_note_pull($data)
    {
        // check type
        switch ($type = $data['type']) {
            case 'post':
                $this->check_access();
                break;
            case 'page':
                $this->check_access('editor');
                break;
            default:
                return new Crash('异常请求');
        }

        // nid
        $nid = (int)$data['nid'];

        // note
        $note = $type == 'post' ?
            get_post($nid, ARRAY_A) : get_page($nid, ARRAY_A);

        if (empty($note)) {
            return new Crash(
                '笔记不存在', 403
            );
        }

        return Nabo_Format::note($note);
    }

    /**
     * @param $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_note_push($data)
    {
        // check type
        switch ($type = $data['type']) {
            case 'post':
                $this->check_access();
                break;
            case 'page':
                $this->check_access('editor');
                break;
            default:
                return new Crash('异常请求');
        }

        // request
        $update = !empty($data['nid']);
        if ($update) {
            if (!get_post($data['nid'])) {
                return new Crash('笔记不存在');
            }
            if (!current_user_can('edit_post', $data['nid'])) {
                return new Crash('没有编辑能力');
            }
        }

        // type
        $target = get_post_type_object($type);
        if (empty($target)) {
            return new Crash('异常请求');
        }

        // note
        $note = array(
            'post_title' => $data['title'],
            'post_content' => $data['content'],
            'post_status' => Nabo_Format::note_status($data['status']),
            'ping_status' => empty($data['allowPing']) ? 'closed' : 'open',
            'comment_status' => empty($data['allowDisc']) ? 'closed' : 'open'
        );

        // nid
        if ($update) {
            $note['ID'] = $data['nid'];
        } else {
            $note['post_type'] = $type;
            $note['post_author'] = $this->liteuser->uid;
        }

        // slug
        if (!empty($data['slug'])) {
            $note['post_name'] = $data['slug'];
        }

        // code
        if (!empty($data['code'])) {
            $note['post_password'] = $data['code'];
        }

        // envoy
        if (!empty($data['envoy'])) {
            $note['page_template'] = $data['envoy'];
        }

        // meta
        $note['post_category'] = [];
        if (!empty($data['meta'])) {
            if (is_numeric($data['meta'])) {
                $note['post_category'][] = $data['meta'];
            } else if (is_array($data['meta'])) {
                $note['post_category'] = $data['meta'];
            }
        }

        // check
        switch ($note['post_status']) {
            case 'draft':
            case 'pending':
                break;
            case 'private':
                if (!current_user_can($target->cap->publish_posts)) {
                    return new Crash('抱歉，您不能在此帖子类型中创建私人帖子');
                }
                break;
            case 'publish':
            case 'future':
                if (!current_user_can($target->cap->publish_posts)) {
                    return new Crash('抱歉，您不允许以此帖子类型发布帖子');
                }
                break;
            default:
                if (!get_post_status_object($note['post_status'])) {
                    $post_data['post_status'] = 'draft';
                }
        }

        // push
        $post_id = $update ? wp_update_post($note, true) : wp_insert_post($note, true);

        // check push
        if (is_wp_error($post_id)) {
            return new Crash(
                $post_id->get_error_message()
            );
        }
        if (empty($post_id)) {
            return new Crash($update ?
                '抱歉，该帖子无法更新' : '抱歉，无法创建该帖子'
            );
        }

        return array(
            'nid' => (int)$post_id
        );
    }

    /**
     * @param $data
     * @return bool|Crash
     */
    public function metcip_kat_note_roll($data)
    {
        // id
        $nid = (int)$data['nid'];

        // check
        $post = get_post($nid, ARRAY_A);
        if (empty($post['ID'])) {
            return new Crash('笔记不存在');
        }

        // check able
        if (!current_user_can('delete_post', $nid)) {
            return new Crash('对不起，您不能删除此帖子');
        }

        // delete
        $result = wp_delete_post($nid);
        if (!$result) {
            return new Crash('对不起，该帖子无法删除');
        }

        return true;
    }

    /**
     * 笔记自定义字段
     *
     * @param $data
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_note_extra($data)
    {
        $this->check_access();

        return new Crash('暂不支持');
    }

    /**
     * @param $data
     * @return KatAry
     * @throws Exception
     */
    public function metcip_kat_discuss_drag($data)
    {
        $this->check_access();

        // query
        $query = [];

        // nid
        if (!empty($data['nid'])) {
            $query['post_id'] = $data['nid'];
        }

        // status
        if ($data['status'] != 'allow') {
            $query['status'] = Nabo_Format::discuss_status($data['status']);
        }

        // paging
        Nabo_Format::paging(
            $data, $offset, $length
        );
        $query['offset'] = $offset;
        $query['number'] = $length;

        return Nabo_Format::discuses(
            get_comments($query)
        );
    }

    /**
     * @param $did
     * @return Exception|KatAny
     * @throws Exception
     */
    public function metcip_kat_discuss_pull($did)
    {
        $this->check_access();

        // check
        if (empty($did)) {
            return new Exception('评论不存在', 404);
        }

        // fetch
        $comment = get_comment($did);
        if (!$comment) {
            return new Crash('评论不存在');
        }

        // check able
        if (!current_user_can('edit_comment', $did)) {
            return new Crash('抱歉，您不能审核或编辑此评论');
        }

        return Nabo_Format::discuss($comment);
    }

    /**
     * @param $data
     * @return Crash|KatAny
     * @throws Exception
     */
    public function metcip_kat_discuss_push($data)
    {
        $this->check_access();

        // nid
        $nid = (int)$data['nid'];

        // rely
        $rely = (int)$data['rely'];

        // check
        if ($data['did'] > 0) {
            return new Crash(
                '抱歉，暂时不支持编辑'
            );
        }

        // check
        if (!comments_open($nid)) {
            return new Crash('抱歉，此项目的评论已关闭');
        }

        // comment
        $comment = array(
            'comment_post_ID' => $nid,
            'comment_parent' => $rely,
            'comment_content' => $data['message']
        );
        $comment['user_ID'] = $this->liteuser->uid;

        if (isset($data['mail'])) {
            $comment['comment_author_email'] = $data['mail'];
        }

        if (isset($data['site'])) {
            $comment['comment_author_url'] = $data['site'];
        }

        if (isset($data['author'])) {
            $comment['comment_author'] = $data['author'];
        }

        // create
        $comment_id = wp_new_comment($comment, true);

        // check
        if (is_wp_error($comment_id)) {
            return new Crash(
                $comment_id->get_error_message()
            );
        }

        // check
        if (empty($comment_id)) {
            return new Crash(
                '评论失败'
            );
        }

        // fetch
        $comment = get_comment($comment_id);
        if (!$comment) {
            return new Crash(
                '获取评论失败'
            );
        }

        return Nabo_Format::discuss($comment);
    }

    /**
     * @param $data
     * @return bool|Crash|Exception
     * @throws Exception
     */
    public function metcip_kat_discuss_mark($data)
    {
        $this->check_access('editor');

        // did
        if (empty($did = $data['did'])) {
            return new Exception(
                '评论不存在'
            );
        }

        // check able
        if (!current_user_can('edit_comment', $did)) {
            return new Crash('抱歉，您不能删除此评论');
        }

        // status
        if (empty($status = $data['status'])) {
            return new Exception(
                '不必更新的情况'
            );
        }

        // status
        $status = Nabo_Format::discuss_status($status);

        // statuses
        $statusList = array_keys(
            get_comment_statuses()
        );

        // check
        if (!in_array($status, $statusList)) {
            return new Crash(
                '评论状态无效: ' . $status
            );
        }

        // update
        $result = wp_update_comment([
            'comment_ID' => $did,
            'comment_approved' => $status
        ]);

        // check
        if (is_wp_error($result)) {
            return new Crash(
                $result->get_error_message()
            );
        }

        return !empty($result);
    }

    /**
     * @param $data
     * @return bool|Crash
     * @throws Exception
     */
    public function metcip_kat_discuss_roll($data)
    {
        $this->check_access('editor');

        // did
        if (empty($did = $data['did'])) {
            return new Crash('评论不存在', 404);
        }

        // check able
        if (!current_user_can('edit_comment', $did)) {
            return new Crash('抱歉，您不能删除此评论');
        }

        return boolval(
            wp_delete_comment($did)
        );
    }

    /**
     * @param $data
     * @return KatAry
     * @throws Exception
     */
    public function metcip_kat_meta_drag($data)
    {
        $this->check_access('editor');

        return Nabo_Format::metas(
            get_categories([
                'get' => 'all'
            ])
        );
    }

    /**
     * @param $data
     * @return Exception|KatAny
     * @throws Exception
     */
    public function metcip_kat_meta_push($data)
    {
        $this->check_access('editor');

        // check able
        if (!current_user_can('manage_categories')) {
            return new Crash(
                '对不起，您不能添加类别'
            );
        }

        if (empty($mid = $data['mid'])) {
            $meta = array(
                'cat_name' => $data['name'],
                'category_nicename' => $data['slug'],
                'category_parent' => intval($data['rely'])
            );

            // insert
            $cat_id = wp_insert_category($meta, true);

            // check
            if (is_wp_error($cat_id)) {
                if ('term_exists' == $cat_id->get_error_code()) {
                    $cat_id = (int)$cat_id->get_error_data();
                } else {
                    return new Crash(
                        '很抱歉，无法创建该类别'
                    );
                }
            }
            if (empty($cat_id)) {
                return new Crash(
                    '很抱歉，无法创建该类别'
                );
            }

            return Nabo_Format::meta(
                get_term($cat_id, 'category')
            );
        } else {
            // fetch
            $term = get_term(
                $mid, 'category'
            );

            // check
            if (empty($term)) {
                return new Crash(
                    '分类不存在'
                );
            }

            // meta
            $meta = array();

            if (isset($data['name'])) {
                $meta['name'] = $data['name'];
            }

            if (isset($data['slug'])) {
                $meta['slug'] = $data['slug'];
            }

            if (isset($data['rely'])) {
                $meta['parent'] = $data['rely'];
            }

            // update
            $term = wp_update_term(
                $mid, 'category', $meta
            );

            // check
            if (is_wp_error($term)) {
                return new Crash(
                    $term->get_error_message()
                );
            }
            if (empty($term)) {
                return new Crash(
                    '抱歉，编辑术语失败'
                );
            }

            return Nabo_Format::meta(
                get_term($mid, 'category')
            );
        }
    }

    /**
     * @param $mid
     * @return bool|Crash|Exception
     * @throws Exception
     */
    public function metcip_kat_meta_roll($mid)
    {
        $this->check_access('editor');

        if (empty($mid)) {
            return new Exception(
                '分类不存在'
            );
        }

        // check
        if (!current_user_can('delete_term', $mid)) {
            return new Crash(
                '对不起，您不能删除此类别'
            );
        }

        // delete
        return boolval(
            wp_delete_term($mid, 'category')
        );
    }

    /**
     * @param $data
     * @return Crash
     * @throws Exception
     */
    public function metcip_kat_meta_sort($data)
    {
        $this->check_access('editor');

        return new Crash(
            '对不起，暂时不支持'
        );
    }

    /**
     * @param $data
     * @return Crash|KatAry
     * @throws Exception
     */
    public function metcip_kat_media_drag($data)
    {
        $this->check_access();

        // check
        if (!current_user_can('upload_files')) {
            return new Crash(
                '对不起，您不能上传文件'
            );
        }

        // paging
        Nabo_Format::paging(
            $data, $offset, $length
        );

        // query
        $query = array(
            'post_type' => 'attachment',
            'offset' => $offset,
            'numberposts' => $length
        );

        return Nabo_Format::medias(
            get_posts($query)
        );
    }

    /**
     * @param $mid
     * @return bool|Exception
     * @throws Exception
     */
    public function metcip_kat_media_roll($mid)
    {
        $this->check_access();

        return new Crash(
            '对不起，暂时不支持'
        );
    }

    /**
     * @param $data
     * @return Crash
     * @throws Exception
     */
    public function metcip_kat_media_clear($data)
    {
        $this->check_access('editor');

        return new Crash(
            '对不起，暂时不支持'
        );
    }

    /**
     * @param $data
     * @return Exception|KatAny
     * @throws Exception
     */
    public function metcip_kat_media_push($data)
    {
        $this->check_access();

        return new Crash(
            '对不起，暂时不支持'
        );
    }
}