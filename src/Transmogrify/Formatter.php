<?php

namespace Transmogrify;

use \Transliterator;

class Formatter
{
    /** @var \Transliterator $transliterator */
    protected $transliterator;

    /**
     * Converts IPB form to Discourse API data.
     *
     * @param array $forum IPB form data
     *
     * @return array
     */
    public function toForum(array $forum)
    {
        return [
            'name'       => $forum['name'],
            'color'      => 'eeeeee',
            'text_color' => '000000',
        ];
    }

    /**
     * Converts IPB user to Discourse API data.
     *
     * @param array $user IPB user data
     *
     * @return array
     */
    public function toUser(array $user)
    {
        return [
            'name'     => $user['members_display_name'],
            'email'    => $user['id'] . '@example.com',
            'password' => md5(openssl_random_pseudo_bytes(12)),
            'username' => $this->toUsername($user['name']),
            'active'   => true,
            'approved' => true,
        ];
    }

    /**
     * Converts IPB topic to Discourse API data.
     *
     * @param int   $categoryId Discourse category ID
     * @param array $topic      IPB topic data
     * @param array $post       IPB OP data
     *
     * @return array
     */
    public function toTopic($categoryId, array $topic, array $post)
    {
        return [
            'title'      => html_entity_decode($topic['title']),
            'raw'        => html_entity_decode($post['post']),
            'category'   => $categoryId,
            'created_at' => $this->toDate($post['post_date']),
        ];
    }

    /**
     * Converts IPB post to Discourse API data.
     *
     * @param int   $topicId Discourse topic ID
     * @param array $post    IPB post data
     *
     * @return array
     */
    public function toPost($topicId, array $post)
    {
        return [
            'raw'        => html_entity_decode($post['post']),
            'topic_id'   => $topicId,
            'created_at' => $this->toDate($post['post_date']),
        ];
    }

    /**
     * Converts username to a proper Discourse username.
     *
     * @param string $username Username to convert
     *
     * @return string
     */
    function toUsername($username)
    {
        if ($this->transliterator === null) {
            $this->transliterator = Transliterator::create('Any-Latin;Latin-ASCII;');
        }

        $username = $this->transliterator->transliterate($username);

        $username = preg_replace('/[^a-z0-9_.-]+/i', '', $username);

        $username = strtolower($username);

        if (!preg_match('/[a-z0-9]$/i', $username)) {
            $username .= (string) rand(0, 9);
        }

        $username = preg_replace('/([_.-])+/', '$1', $username);

        return $username;
    }

    /**
     * Converts timestamp to a proper post creation date.
     *
     * @param int $timestamp
     *
     * @return int
     */
    function toDate($timestamp)
    {
        return date('Y-m-d', $timestamp);
    }
}