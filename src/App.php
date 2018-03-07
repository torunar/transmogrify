<?php

namespace Transmogrify;

class App
{
    /** @var \Transmogrify\Logger $logger */
    protected $logger;

    /** @var \Transmogrify\ArgsParser $argsParser */
    protected $argsParser;

    /** @var \Transmogrify\Ipb $ipb */
    protected $ipb;

    /** @var \Transmogrify\ApiRequestor $api */
    protected $api;

    /** @var \Transmogrify\Formatter $formatter */
    protected $formatter;

    /** @var array $forumsIds */
    protected $forumsIds;

    /** @var int|null $topicsLimit */
    protected $topicsLimit;

    /** @var int|null $postsLimit */
    protected $postsLimit;

    /** @var \Transmogrify\EventManager $eventManager */
    protected $eventManager;

    /** @var array $dbSettings */
    protected $dbSettings;

    /** @var string $ipbAddress */
    protected $ipbAddress;

    /** @var string $apiKey */
    protected $apiKey;

    /** @var string $discourseAddress */
    protected $discourseAddress;

    /**
     * App constructor.
     *
     * @param       $argc
     * @param array $argv
     *
     * @throws \Exception
     */
    public function __construct($argc, array $argv = [])
    {
        $this->logger = new Logger();

        $this->argsParser = new ArgsParser($argc);

        list($this->dbSettings,
            $this->ipbAddress,
            $this->apiKey,
            $this->discourseAddress,
            $this->forumsIds,
            $this->topicsLimit,
            $this->postsLimit) = $this->argsParser->parse($argv);

        $this->ipb = new Ipb($this->dbSettings, $this->ipbAddress);

        $this->api = new ApiRequestor($this->discourseAddress, $this->apiKey);

        $this->formatter = new Formatter();

        $this->eventManager = new EventManager();
    }

    /**
     * @return int
     *
     * @throws \Transmogrify\ApiException
     */
    public function run()
    {
        $this->triggerEvent('preRun');

        $usersConversionMap = [];

        $forumsQuery = $this->ipb->getForums($this->forumsIds);
        $forumsCounter = 0;
        while ($forum = $forumsQuery->fetch_assoc()) {

            $forumData = $this->formatter->toForum($forum);

            list($respDecoded,) = $this->api->request('categories', $forumData);

            $categoryId = (int) $respDecoded['category']['id'];

            $this->triggerEvent('postForumCreated', $forum, $forumData, $respDecoded, $categoryId);

            $this->logger->setProgress('Forums', ++$forumsCounter, $forumsQuery->num_rows);

            $topicsQuery = $this->ipb->getTopics($forum['id'], $this->topicsLimit);
            $topicsCounter = 0;
            while ($topic = $topicsQuery->fetch_assoc()) {

                $topicId = null;

                $postsQuery = $this->ipb->getPosts($topic['id'], $this->postsLimit);
                $postsCounter = 0;
                while ($post = $postsQuery->fetch_assoc()) {

                    $attachments = $this->ipb->getAttachments($post['id']);

                    if (!isset($usersConversionMap[$post['user_id']])) {

                        $user = $this->ipb->getUser($post['user_id']);

                        $userData = $this->formatter->toUser($user);

                        $this->api->request('users', $userData);

                        $usersConversionMap[$user['id']] = $userData['username'];
                    }

                    $username = $usersConversionMap[$post['user_id']];

                    if ($postsCounter === 0) {

                        $topicData = $this->formatter->toTopic($categoryId, $topic, $post, $attachments);

                        list($respDecoded,) = $this->api->request('posts', $topicData, 'post', $username);

                        $topicId = $respDecoded['topic_id'];

                        $this->triggerEvent('postTopicCreated', $topic, $topicData, $respDecoded, $topicId);

                        $this->logger->setProgress("\tTopics", ++$topicsCounter, $topicsQuery->num_rows);
                        $this->logger->setProgress("\t\tPosts", ++$postsCounter, $postsQuery->num_rows);

                        continue;
                    }

                    $postData = $this->formatter->toPost($topicId, $post, $attachments);

                    $this->api->request('posts', $postData, 'post', $username);

                    $this->logger->setProgress("\t\tPosts", ++$postsCounter, $postsQuery->num_rows);
                }
            }
        }

        $this->triggerEvent('postRun');

        return 0;
    }

    /**
     * Triggers an event.
     */
    public function triggerEvent()
    {
        $funcArgs = func_get_args();

        $eventName = array_shift($funcArgs);

        array_unshift($funcArgs, $this);

        if (method_exists($this->eventManager, $eventName)) {
            call_user_func_array([$this->eventManager, $eventName], $funcArgs);
        }
    }

    /**
     * @return \Transmogrify\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return string
     */
    public function getDiscourseAddress()
    {
        return $this->discourseAddress;
    }
}