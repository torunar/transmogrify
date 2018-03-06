<?php

namespace Transmogrify;

use Exception;

class Ipb
{
    /** @var \mysqli $db */
    protected $db;

    /** @var string $prefix */
    protected $prefix;

    /** @var string $address */
    protected $address;

    /**
     * Ipb constructor.
     *
     * @param array  $connectionSettings Database connection properties: host, username, password, name
     * @param string $address            Forum address (specified as schema://host/path)
     *
     * @throws \Exception
     */
    public function __construct(array $connectionSettings, $address)
    {
        $this->db = @mysqli_connect(
            $connectionSettings['host'],
            $connectionSettings['username'],
            $connectionSettings['password'],
            $connectionSettings['name']
        );

        $this->prefix = $connectionSettings['prefix'];

        $this->address = ltrim($address, '/');

        if (!$this->db) {
            throw new Exception('DB connection failed');
        }
    }

    public function __destruct()
    {
        $this->db->close();
    }

    /**
     * Fetches forums.
     *
     * @param array|null $forumsIds Forum IDs to fetch
     *
     * @return bool|\mysqli_result
     */
    public function getForums(array $forumsIds = null)
    {
        return $this->db->query(
            sprintf(
                'SELECT id, name FROM %sforums %s',
                $this->prefix,
                $forumsIds
                    ? sprintf('WHERE id IN (%s)', implode(',', $forumsIds))
                    : ''
            )
        );
    }

    /**
     * Fetches topics.
     *
     * @param int      $forumId Forum ID to fetch from
     * @param int|null $limit   Topics per forum limit
     *
     * @return bool|\mysqli_result
     */
    public function getTopics($forumId, $limit = null)
    {
        return $this->db->query(
            sprintf(
                'SELECT tid AS id, starter_id AS user_id, start_date, forum_id, title FROM %stopics'
                . ' WHERE forum_id = %d'
                . ' ORDER BY start_date DESC'
                . ' %s',
                $this->prefix,
                $forumId,
                $this->limit($limit)
            )
        );
    }

    /**
     * Fetches posts.
     *
     * @param int      $topicId Topic ID to fetch from
     * @param int|null $limit   Posts per topic limit
     *
     * @return bool|\mysqli_result
     */
    public function getPosts($topicId, $limit = null)
    {
        return $this->db->query(
            sprintf(
                'SELECT pid AS id, author_id AS user_id, post, post_date FROM %sposts'
                . ' WHERE topic_id = %d AND pdelete_time = 0'
                . ' ORDER BY post_date ASC'
                . ' %s',
                $this->prefix,
                $topicId,
                $this->limit($limit)
            )
        );
    }

    /**
     * Fetched user.
     *
     * @param int $userId User ID
     *
     * @return array
     */
    public function getUser($userId)
    {
        if ($userId == 0) {
            return [
                'id'                   => 0,
                'name'                 => 'guest',
                'members_display_name' => 'guest',
                'email'                => 'guest@example.com',
            ];
        }

        $query = $this->db->query(
            sprintf(
                'SELECT member_id AS id, name, members_display_name, email FROM %smembers'
                . ' WHERE member_id = %d',
                $this->prefix,
                $userId
            )
        );

        return $query->fetch_assoc();
    }

    /**
     * Provides limit expression for SQL query.
     *
     * @param int|null $limit Amount of objects
     *
     * @return string
     */
    protected function limit($limit = null)
    {
        if ((int) $limit > 0) {
            return sprintf('LIMIT %d', $limit);
        }

        return '';
    }

    /**
     * Fetches post attachments.
     *
     * @param int $postId Post ID
     *
     * @return array
     */
    public function getAttachments($postId)
    {
        $query = $this->db->query(
            sprintf(
                "SELECT attach_file AS name, CONCAT('%s', '/uploads/', attach_location) AS location FROM %sattachments"
                . " WHERE attach_rel_module = 'post' AND attach_rel_id = %d",
                $this->address,
                $this->prefix,
                $postId
            )
        );

        return $query->fetch_all(MYSQLI_ASSOC);
    }
}
