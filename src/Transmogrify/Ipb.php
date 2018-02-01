<?php

namespace Transmogrify;

use Exception;

class Ipb
{
    protected $db;

    /**
     * Ipb constructor.
     *
     * @param array $connectionSettings
     *
     * @throws \Exception
     */
    public function __construct(array $connectionSettings)
    {
        $this->db = @mysqli_connect(
            $connectionSettings['host'],
            $connectionSettings['username'],
            $connectionSettings['password'],
            $connectionSettings['name']
        );

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
                'SELECT id, name FROM cfforums %s',
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
                'SELECT tid AS id, starter_id AS user_id, start_date, forum_id, title FROM cftopics'
                . ' WHERE forum_id = %d'
                . ' ORDER BY start_date DESC'
                . ' %s',
                $forumId,
                $limit
                    ? "LIMIT {$limit}"
                    : ''
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
                'SELECT author_id AS user_id, post, post_date FROM cfposts'
                . ' WHERE topic_id = %d'
                . ' ORDER BY post_date ASC'
                . ' %s',
                $topicId,
                $limit
                    ? "LIMIT {$limit}"
                    : ''
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
        $query = $this->db->query(
            sprintf(
                'SELECT member_id AS id, name, members_display_name, email FROM cfmembers'
                . ' WHERE member_id = %d',
                $userId
            )
        );

        return $query->fetch_assoc();
    }
}