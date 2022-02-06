<?php

class Nabo_Format
{
    /**
     * @param $status
     * @return string
     */
    public static function note_status($status)
    {
        switch ($status) {
            case 'open':
                return 'publish';
            case 'self':
                return 'private';
            case 'draft':
                return 'draft';
            case 'close':
                return 'pending';
        }
        return 'publish';
    }

    /**
     * @param $status
     * @return string
     */
    public static function note_status_by($status)
    {
        switch ($status) {
            case 'publish':
                return 'open';
            case 'private':
                return 'self';
            case 'draft':
            case 'auto-draft':
                return 'draft';
            case 'pending':
                return 'close';
            case 'trash':
                return 'trash';
        }
        return 'other';
    }

    /**
     * @param $status
     * @return string
     */
    public static function dynamic_status($status)
    {
        switch ($status) {
            case 'open':
                return 'publish';
            case 'self':
                return 'private';
            case 'hide':
                return 'hidden';
        }
        return 'publish';
    }

    /**
     * @param $status
     * @return string
     */
    public static function dynamic_status_by($status)
    {
        switch ($status) {
            case 'publish':
                return 'open';
            case 'private':
                return 'self';
            case 'hidden':
                return 'hide';
        }
        return 'open';
    }

    /**
     * @param $status
     * @return string
     */
    public static function discuss_status($status)
    {
        switch ($status) {
            case 'open':
                return 'approve';
            case 'spam':
                return 'spam';
            case 'close':
                return 'hold';
        }
        return 'approve';
    }

    /**
     * @param $status
     * @return string
     */
    public static function discuss_status_by($status)
    {
        switch ($status) {
            case 'approve':
                return 'open';
            case 'spam':
                return 'spam';
            case 'hold':
                return 'close';
        }
        return 'open';
    }

    /**
     * @param $data
     * @param $offset
     * @param $length
     */
    public static function paging($data, &$offset, &$length)
    {
        $offset = intval($data['offset']);
        $length = $offset > 0 ? $offset : 0;

        $length = intval($data['length']);
        $length = $length > 0 ? $length : 10;
    }

    /**
     * @param $user
     * @param false $token
     * @return array
     */
    public static function user($user, $token = false)
    {
        $target = array(
            'uid' => (int)$user->ID,
            'name' => (string)$user->user_login,
            'mail' => (string)$user->user_email,
            'nickname' => (string)$user->nickname,
            'site' => (string)$user->user_url,
            'role' => (string)$user->roles[0],
            'access' => '',
            'touched' => time(),
            'created' => strtotime($user->user_registered),
            'modified' => time(),
        );
        if ($token) {
            $target['token'] = $token;
        }
        return $target;
    }

    /**
     * @param $notes
     * @param bool $more
     * @return KatAry
     */
    public static function notes($notes, $more = false)
    {
        $arg = new KatAry();
        foreach ($notes as $note) {
            $arg->add(
                self::note(
                    $note, $more
                )
            );
        }
        return $arg;
    }

    /**
     * @param $note
     * @param bool $more
     * @return KatAny
     */
    public static function note($note, $more = true)
    {
        $nid = intval($note['ID']);

        // slug
        $slug = $note['post_name'];
        $note['slug'] = urlencode($slug);

        // fields
        $fields = [];

        // metas
        $meta = 0;
        $metas = get_the_category($nid);
        if (empty($metas)) {
            $category = '';
        } else {
            list($cate) = $metas;
            $meta = (int)$cate->term_id;
            $category = (string)$cate->name;
            unset($cate);
        }

        // tags
        $tags = [];
        $label = wp_get_post_tags($nid);
        foreach ($label as $row) {
            $tags[] = $row->name;
        }
        $tags = implode(',', $tags);
        unset($row);

        // permalink
        $permalink = (string)get_permalink($nid);

        // content
        if ($more && isset($note['post_content'])) {
            if (strpos($note['post_content'], '<!--markdown-->') === 0) {
                $note['post_content'] = substr($note['post_content'], 15);
            }
        } else {
            unset($note['post_content']);
        }

        $type = (string)$note['post_type'];
        $code = (string)$note['post_password'];
        if (empty($code)) {
            $status = self::note_status_by(
                (string)$note['post_status']
            );
        } else {
            $status = 'code';
        }

        return new KatAny([
            'nid' => $nid,
            'uid' => (int)$note['post_author'],
            'title' => (string)$note['post_title'],
            'content' => (string)$note['post_content'],
            'type' => $type,
            'slug' => (string)$slug,
            'code' => $code,
            'rely' => (int)$note['post_parent'],
            'meta' => $meta,
            'tags' => $tags,
            'order' => 0,
            'envoy' => (string)$note['page_template'],
            'status' => $status,
            'extras' => $fields,
            'category' => $category,
            'permalink' => (string)$permalink,
            'created' => strtotime($note['post_date']) - 28800,
            'modified' => strtotime($note['post_modified']) - 28800,

            'allowPing' => (int)($note['ping_status'] == 'open'),
            'allowFeed' => 0,
            'allowDisc' => (int)($note['comment_status'] == 'open'),
        ], 'Note');
    }

    /**
     * @param $discuses
     * @return KatAry
     */
    public static function discuses($discuses)
    {
        $ary = new KatAry();
        foreach ($discuses as $discuss) {
            $ary->add(
                self::discuss($discuss)
            );
        }
        return $ary;
    }

    /**
     * @param $discuss
     * @return KatAny
     */
    public static function discuss($discuss)
    {
        switch ($discuss->comment_approved) {
            case '0':
                $status = 'hold';
                break;
            case '1':
                $status = 'approve';
                break;
            case 'spam':
                $status = 'spam';
                break;
            default:
                $status = $discuss->comment_approved;
        }
        return new KatAny([
            'did' => (int)$discuss->comment_ID,
            'nid' => (int)$discuss->comment_post_ID,
            'uid' => (int)$discuss->user_id,
            'mail' => (string)$discuss->comment_author_email,
            'site' => (string)$discuss->comment_author_url,
            'author' => (string)$discuss->comment_author,
            'message' => (string)$discuss->comment_content,
            'status' => self::discuss_status_by($status),
            'agent' => (string)$discuss->comment_agent,
            'address' => (string)$discuss->comment_author_IP,
            'rely' => (int)$discuss->comment_parent,
            'title' => '',
            'created' => strtotime($discuss->comment_date) - 28800,
            'modified' => 0
        ], 'Discuss');
    }

    /**
     * @param $metas
     * @return KatAry
     */
    public static function metas($metas)
    {
        $ary = new KatAry();
        foreach ($metas as $meta) {
            $ary->add(
                self::meta($meta)
            );
        }
        return $ary;
    }

    /**
     * @param $meta
     * @return KatAny
     */
    public static function meta($meta)
    {
        return new KatAny([
            'mid' => (int)$meta->term_id,
            'name' => (string)$meta->name,
            'slug' => (string)$meta->slug,
            'rely' => (int)$meta->parent,
            'order' => 0
        ], 'Meta');
    }

    /**
     * @param $medias
     * @return KatAry
     */
    public static function medias($medias)
    {
        $ary = new KatAry();
        foreach ($medias as $media) {
            $ary->add(
                self::media($media)
            );
        }
        return $ary;
    }

    /**
     * @param $media
     * @return KatAny
     */
    public static function media($media)
    {
        return new KatAny([
            'mid' => (int)$media->ID,
            'name' => (string)$media->post_title,
            'link' => (string)$media->guid,
            'path' => '',
            'mime' => (string)$media->post_mime_type,
            'length' => 0,
            'created' => strtotime($media->post_date) - 28800,
            'modified' => strtotime($media->post_modified) - 28800,
        ], 'Media');
    }

    /**
     * @param $dynamics
     * @return KatAry
     */
    public static function dynamics($dynamics)
    {
        $ary = new KatAry();
        foreach ($dynamics as $dynamic) {
            $ary->add(
                self::dynamic($dynamic)
            );
        }
        return $ary;
    }

    /**
     * @param $dynamic
     * @return KatAny
     */
    public static function dynamic($dynamic)
    {
        return new KatAny([
            'did' => (int)$dynamic['did'],
            'uid' => (int)$dynamic['uid'],
            'title' => (string)$dynamic['title'],
            'content' => (string)$dynamic['content'],
            'status' => Nabo_Format::dynamic_status_by(
                (string)$dynamic['status']
            ),
            'created' => (int)$dynamic['created'],
            'modified' => (int)$dynamic['modified'],
            'permalink' => (string)$dynamic['permalink'],
        ], 'Dynamic');
    }

    /**
     * @param $friends
     * @return KatAry
     */
    public static function friends($friends)
    {
        $ary = new KatAry();
        foreach ($friends as $friend) {
            $ary->add(
                self::friend($friend)
            );
        }
        return $ary;
    }

    /**
     * @param $friend
     * @return KatAny
     */
    public static function friend($friend)
    {
        return new KatAny([
            'fid' => (int)$friend['lid'],
            'name' => (string)$friend['name'],
            'link' => (string)$friend['link'],
            'image' => (string)$friend['image'],
            'intro' => (string)$friend['intro'],
            'team' => (string)$friend['team'],
            'order' => (int)$friend['order'],
            'extra' => (string)$friend['extra'],
        ], 'Friend');
    }

    /**
     * @param $uid
     * @return int
     */
    public static function create_words($uid)
    {
        $count = 0;
        $query = [
            'post_type' => 'post',
            'numberposts' => 99999
        ];

        foreach (wp_get_recent_posts($query) as $row) {
            if ($row['post_author'] == $uid) {
                $count += mb_strlen($row['post_title'], 'UTF-8');
                $count += mb_strlen($row['post_content'], 'UTF-8');
            }
        }

        return $count;
    }
}