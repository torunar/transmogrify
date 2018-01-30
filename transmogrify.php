<?php

date_default_timezone_set('UTC');

if ($argc < (5 + 1)) {
    die("Usage:\n\tphp transmogrify.php dbHost dbUser dbPassword dbName apiKey [discourseAddress] [forumIds] [topicsLimit] [postsLimit]\n");
}

$dbSettings = [
    'host'     => $argv[1],
    'username' => $argv[2],
    'password' => $argv[3],
    'name'     => $argv[4],
];

$apiKey = $argv[5];

$discourse = isset($argv[6])
    ? $argv[6]
    : 'http://localhost:4000';
$forumsIds = isset($argv[7])
    ? explode(',', $argv[7])
    : [];
$topicsLimit = isset($argv[8])
    ? (int) $argv[8]
    : null;
$postsLimit = isset($argv[9])
    ? (int) $argv[9]
    : null;

$db = mysqli_connect(
    $dbSettings['host'],
    $dbSettings['username'],
    $dbSettings['password'],
    $dbSettings['name']
);

if (!$db) {
    __dump('DB connection failed', $dbSettings);
}

$usersConversionMap = [];

$forumsQuery = __getForums($db, $forumsIds);
$forumsCounter = 0;
while ($forum = $forumsQuery->fetch_assoc()) {

    $forumData = __toForum($forum);

    try {
        list($respDecoded,) = __api($discourse, 'categories', $apiKey, $forumData);
    } catch (Exception $e) {
        __dump($e->getMessage(), $forumData);
    }

    $categoryId = (int) $respDecoded['category']['id'];

    __progress('Forums', ++$forumsCounter, $forumsQuery->num_rows);

    $topicsQuery = __getTopics($db, $forum['id'], $topicsLimit);
    $topicsCounter = 0;
    while ($topic = $topicsQuery->fetch_assoc()) {

        $topicId = null;

        $postsQuery = __getPosts($db, $topic['id'], $postsLimit);
        $postsCounter = 0;
        while ($post = $postsQuery->fetch_assoc()) {

            if (!isset($usersConversionMap[$post['user_id']])) {

                $user = __getUser($db, $post['user_id']);

                $userData = __toUser($user);

                try {
                    list($respDecoded,) = __api($discourse, 'users', $apiKey, $userData);
                    /*
                    list($respDecoded,) = __api($discourse, 'admin/users/' . $respDecoded['user_id'] . '/trust_level',
                        $apiKey, [
                            'level' => 2,
                        ]);
                    */
                } catch (Exception $e) {
                    __dump($e->getMessage(), $userData);
                }

                $usersConversionMap[$user['id']] = $userData['username'];
            }

            $username = $usersConversionMap[$post['user_id']];

            if ($postsCounter === 0) {

                $topicData = __toTopic($categoryId, $topic, $post);

                try {
                    list($respDecoded,) = __api($discourse, 'posts', $apiKey, $topicData, 'post', $username);
                } catch (Exception $e) {
                    __dump($e->getMessage(), $topicData);
                }

                $topicId = $respDecoded['topic_id'];

                __progress('Topics', ++$topicsCounter, $topicsQuery->num_rows);
                __progress('Posts', ++$postsCounter, $postsQuery->num_rows);

                continue;
            }

            $postData = __toPost($topicId, $post);

            try {
                __api($discourse, 'posts', $apiKey, $postData, 'post', $username);
            } catch (Exception $e) {
                __dump($e->getMessage(), $postData);
            }

            __progress('Posts', ++$postsCounter, $postsQuery->num_rows);
        }
    }
}

/**
 * Fetches forums.
 *
 * @param \mysqli    $db        DB to fetch from
 * @param array|null $forumsIds Forum IDs to fetch
 *
 * @return bool|\mysqli_result
 */
function __getForums(mysqli $db, array $forumsIds = null)
{
    return mysqli_query(
        $db,
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
 * @param \mysqli  $db      DB to fetch from
 * @param int      $forumId Forum ID to fetch from
 * @param int|null $limit   Topics per forum limit
 *
 * @return bool|\mysqli_result
 */
function __getTopics(mysqli $db, $forumId, $limit = null)
{
    return mysqli_query(
        $db,
        sprintf(
            'SELECT tid AS id, starter_id AS user_id, start_date, forum_id, title FROM cftopics'
            . ' WHERE forum_id = %d'
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
 * @param \mysqli  $db      DB to fetch from
 * @param int      $topicId Topic ID to fetch from
 * @param int|null $limit   Posts per topic limit
 *
 * @return bool|\mysqli_result
 */
function __getPosts(mysqli $db, $topicId, $limit = null)
{
    return mysqli_query(
        $db,
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
 * @param \mysqli $db
 * @param int     $userId
 *
 * @return array
 */
function __getUser(mysqli $db, $userId)
{
    $query = mysqli_query(
        $db,
        sprintf(
            'SELECT member_id AS id, name, members_display_name, email FROM cfmembers'
            . ' WHERE member_id = %d',
            $userId
        )
    );

    return $query->fetch_assoc();
}

/**
 * Converts IPB form to Discourse API data.
 *
 * @param array $forum IPB form data
 *
 * @return array
 */
function __toForum(array $forum)
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
function __toUser(array $user)
{
    return [
        'name'     => $user['members_display_name'],
        'email'    => $user['id'] . '@example.com',
        'password' => md5(openssl_random_pseudo_bytes(12)),
        'username' => __username($user['name']),
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
function __toTopic($categoryId, array $topic, array $post)
{
    return [
        'title'      => html_entity_decode($topic['title']),
        'raw'        => html_entity_decode($post['post']),
        'category'   => $categoryId,
        'created_at' => __date($post['post_date']),
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
function __toPost($topicId, array $post)
{
    return [
        'raw'        => html_entity_decode($post['post']),
        'topic_id'   => $topicId,
        'created_at' => __date($post['post_date']),
    ];
}

/**
 * Performs API request to Discourse instance.
 *
 * @param string $address       Instance net address
 * @param string $method        API method to request
 * @param string $key           API key
 * @param array  $data          Data to send in request
 * @param string $requestMethod Request method (get, post)
 * @param string $username      Username to perform request with
 *
 * @return array Decoded and raw response
 * @throws \Exception When request failed
 */
function __api($address, $method, $key, array $data = [], $requestMethod = 'post', $username = 'system')
{
    static $callCount = 0;
    if (++$callCount % 5 === 0) {
        printf("[%s]\t...lounge music plays...\n", __now());
        $callCount = 0;
        sleep(15);
    }

    $url = sprintf(
        '%s/%s.json?api_username=%s&api_key=%s',
        rtrim($address, '/'),
        $method,
        $username,
        $key
    );

    if ($requestMethod === 'get' && $data) {
        $url .= '&' . http_build_query($data);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SAFE_UPLOAD    => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($requestMethod),
    ]);

    if ($requestMethod === 'post'
        || $requestMethod == 'put'
    ) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    $errorNumber = curl_errno($ch);
    $errorMessage = curl_exec($ch);
    curl_close($ch);

    if ($errorNumber) {
        throw new Exception($errorMessage, $errorNumber);
    }

    $responseDecoded = json_decode($response, true);
    
    if ($responseDecoded === null) {
        throw new Exception($response);
    }

    if (!empty($responseDecoded['errors'])) {
        foreach ($responseDecoded['errors'] as $fieldName => $fieldError) {
            $errorText = $fieldError;
            if (is_array($fieldError)) {
                $errorText = '';
                foreach ($fieldError as $errorMessage) {
                    $errorText .= sprintf("%s: %s\n", $fieldName, $errorMessage);
                }
            }
            throw new Exception($errorText);
        }
    }

    return array($responseDecoded, $response);
}

/**
 * Converts username to a proper Discourse username.
 *
 * @param string $username Username to convert
 *
 * @return string
 */
function __username($username)
{
    static $transliterator;

    if ($transliterator === null) {
        $transliterator = Transliterator::create('Any-Latin;Latin-ASCII;');
    }

    $username = $transliterator->transliterate($username);

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
function __date($timestamp)
{
    return date('Y-m-d', $timestamp);
}

/**
 * Provides formatted date-time for log.
 *
 * @return string
 */
function __now()
{
    return date('Y-m-d H:i:s');
}

/**
 * var_dump's passed values and dies.
 */
function __dump()
{
    call_user_func_array('var_dump', func_get_args());

    die;
}

/**
 * Prints progress for the entity transference.
 *
 * @param string $entity  Entity name
 * @param int    $current Current step
 * @param int    $total   Total steps
 */
function __progress($entity, $current, $total)
{
    printf("[%s]\t%s: %d/%d\n", __now(), $entity, $current, $total);
}
