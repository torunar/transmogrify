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
            'email'    => $user['email'],
            'password' => md5(openssl_random_pseudo_bytes(12)),
            'username' => $this->toUsername($user['name']),
            'active'   => true,
            'approved' => true,
        ];
    }

    /**
     * Converts IPB topic to Discourse API data.
     *
     * @param int   $categoryId  Discourse category ID
     * @param array $topic       IPB topic data
     * @param array $post        OP data
     * @param array $attachments OP attachments
     *
     * @return array
     */
    public function toTopic($categoryId, array $topic, array $post, array $attachments = [])
    {
        $openingPost = $this->toPost(0, $post, $attachments);
        $openingPost['title'] = html_entity_decode($topic['title']);
        $openingPost['category'] = $categoryId;
        unset($openingPost['topic_id']);

        return $openingPost;
    }

    /**
     * Converts IPB post to Discourse API data.
     *
     * @param int   $topicId     Discourse topic ID
     * @param array $post        IPB post data
     * @param array $attachments Post attachments
     *
     * @return array
     */
    public function toPost($topicId, array $post, array $attachments = [])
    {
        $text = trim($post['post']);

        $text = html_entity_decode($text);

        foreach ($attachments as $attachment) {
            $text .= $this->toAttachment($attachment);
        }

        $text = $this->replacePlaceholders($text);

        if ($this->isTypographical($text)) {
            $text .= '&nbsp;';
        }

        return [
            'raw'        => $text,
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

        $username = strtolower($username);

        // name can contain only alphanumerics, dots, dashes and underscores
        $username = preg_replace('/[^a-z0-9_.-]+/i', '', $username);

        // name must start with alphanumeric or underscore
        $username = preg_replace('/^[^a-z0-9_]+/', '', $username);

        // name must end with alphanumeric
        if (!preg_match('/[a-z0-9]$/i', $username)) {
            $username = $this->randomizeUsername($username);
        }

        // name must not contain consequential dots, dashes or underscores
        $username = preg_replace('/([_.-])+/', '$1', $username);

        // FIXME: restricted usernames from Discourse settings must be used instead
        if ($username === 'admin') {
            $username = 'system';
        }

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

    /**
     * Converts IPB attachment to attachment link.
     *
     * @param array $attachment IPB attachment (must contain `name` and `location`)
     *
     * @return string Attachment link
     */
    protected function toAttachment(array $attachment)
    {
        return sprintf(
            '<p><a href="%s">%s</a></p>',
            $attachment['location'],
            $attachment['name']
        );
    }

    /**
     * Replaces some IPB variables in post text.
     *
     * @param string $text Post text
     *
     * @return string
     */
    protected function replacePlaceholders($text)
    {
        return strtr($text, [
            '<#EMO_DIR#>' => 'default',
        ]);
    }

    /**
     * Checks if text contains only typographical symbols.
     *
     * @param string $text
     *
     * @return false|int
     */
    protected function isTypographical($text)
    {
        $text = trim($text);

        $typography = [
            '!',
            '@',
            '#',
            '$',
            '%',
            ':',
            '^',
            ';',
            '*',
            '(',
            ')',
            '[',
            ']',
            '{',
            '}',
            '<',
            '>',
            '-',
            '_',
            '=',
            '+',
            '`',
            '~',
            '.',
            ',',
            '/',
            '\\',
        ];

        return (bool) preg_match('/^[\\' . implode('\\', $typography) . ']+$/', $text);
    }

    /**
     * Adds random number to the end of the username.
     *
     * @param string $username Username
     *
     * @return string
     */
    public function randomizeUsername($username)
    {
        $username .= (string) rand(0, 9);

        return $username;
    }
}