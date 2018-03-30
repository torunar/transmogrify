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

    /** @var array $usersConversionMap */
    protected $usersConversionMap = [];

    /** @var array $forumsConversionMap */
    protected $forumsConversionMap = [];

    /** @var array $topicsConversionMap */
    protected $topicsConversionMap = [];

    /** @var array $postsConversionMap */
    protected $postsConversionMap = [];

    /** @var bool $isStateRestored */
    protected $isStateRestored = false;

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

        $forumsQuery = $this->ipb->getForums($this->forumsIds);
        $forumsCounter = 0;
        while ($forum = $forumsQuery->fetch_assoc()) {

            $forumData = $this->formatter->toForum($forum);

            $respDecoded = null;
            if (!isset($this->forumsConversionMap[$forum['id']])) {
                list($respDecoded,) = $this->api->request('categories', $forumData);
                $this->forumsConversionMap[$forum['id']] = (int) $respDecoded['category']['id'];
            }

            $categoryId = $this->forumsConversionMap[$forum['id']];

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

                    $respDecoded = null;
                    if (!isset($this->usersConversionMap[$post['user_id']])) {

                        $user = $this->ipb->getUser($post['user_id']);
                        $userData = $this->formatter->toUser($user);

                        // check if username is already used
                        $isValid = false;
                        while (!$isValid) {
                            $isValid = array_search($userData['username'], $this->usersConversionMap) === false;

                            if (!$isValid) {
                                $userData['username'] = $this->formatter->randomizeUsername($userData['username']);
                            }
                        }

                        list($respDecoded,) = $this->api->request('users', $userData);
                        $this->usersConversionMap[$user['id']] = $userData['username'];
                    }

                    $username = $this->usersConversionMap[$post['user_id']];

                    if ($postsCounter === 0) {

                        $topicData = $this->formatter->toTopic($categoryId, $topic, $post, $attachments);

                        $respDecoded = null;
                        if (!isset($this->topicsConversionMap[$topic['id']])) {
                            list($respDecoded,) = $this->api->request('posts', $topicData, 'post', $username);
                            $this->topicsConversionMap[$topic['id']] = (int) $respDecoded['topic_id'];
                        }

                        $topicId = $this->topicsConversionMap[$topic['id']];

                        $this->triggerEvent('postTopicCreated', $topic, $topicData, $respDecoded, $topicId);

                        $this->logger->setProgress('  Topics', ++$topicsCounter, $topicsQuery->num_rows);
                        $this->logger->setProgress('    Posts', ++$postsCounter, $postsQuery->num_rows);

                        continue;
                    }

                    $postData = $this->formatter->toPost($topicId, $post, $attachments);

                    $respDecoded = null;
                    if (!isset($this->postsConversionMap[$post['id']])) {
                        list($respDecoded,) = $this->api->request('posts', $postData, 'post', $username);
                        $this->postsConversionMap[$post['id']] = (int) $respDecoded['id'];
                    }

                    $this->logger->setProgress('    Posts', ++$postsCounter, $postsQuery->num_rows);
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

    /**
     * Provides path to a file to store application state in it.
     *
     * @return string
     */
    protected function getStatePath()
    {
        return __DIR__ . '/../var/transmogrify.state';
    }

    /**
     * Restores application state on startup.
     */
    public function restoreState()
    {
        $statePath = $this->getStatePath();

        if (file_exists($statePath)) {
            $state = require_once $statePath;
            $this->forumsConversionMap = $state['forums'];
            $this->topicsConversionMap = $state['topics'];
            $this->postsConversionMap = $state['posts'];
            $this->usersConversionMap = $state['users'];
            unset($state);

            $this->isStateRestored = true;
        }
    }

    /**
     * Saves application state on shutdown.
     */
    public function saveState()
    {
        $restore = [
            'forums' => $this->forumsConversionMap,
            'topics' => $this->topicsConversionMap,
            'posts'  => $this->postsConversionMap,
            'users'  => $this->usersConversionMap,
        ];

        $statePath = $this->getStatePath();
        if (!is_dir(dirname($statePath))) {
            mkdir(dirname($statePath));
        }

        $tpl = '<?php'
            . ' $restore = %s;'
            . ' return $restore;';

        file_put_contents($statePath, sprintf($tpl, var_export($restore, 1)));
    }

    /**
     * Clears stored application state.
     */
    protected function resetState()
    {
        $statePath = $this->getStatePath();

        if (file_exists($statePath)) {
            unlink($statePath);
        }
    }
}