# Transmogrify

## About

**Transmogrify** allows you to transfer your InVision (ex. IP.Board) forum to the Discourse forum.

The tool fetches forums, topics, posts and users from your old forum's database and transfers them via Discourse RESTful API.

## Discourse configuration

Before running a tool, the Discourse should be configured to minimise side effects, induced by the built-in rate limits and anti-spam protection system.

Log in into your Discourse administration panel, go to the **Settings** tab and set the following settings accordingly:

```
rate limit create topic             0
rate limit create post              0
rate limit new user create topic    0
rate limit new user create post     0
max topics per day                  900000 (higher = better)
max topics in first day             900000 (higher = better)

newuser max replies per topic       100    (higher = better)
newuser max mentions per post       100    (higher = better)
newuser max links                   100    (higher = better)
newuser max images                  100    (higher = better)
newuser max attachments             5      (higher = better)
newuser spam host threshold         30     (higher = better)

min post length                     5       (lower = better)
min first post length               5       (lower = better)
body min entropy                    1
title min entropy                   1
min topic title length              3
min title similar length            1024
```

You will also need to create an API key to interact with your Discourse forum.

Log in into your Discourse administration panel, go to the **API** tab and press the button with the key icon to generate an API key.

## Usage

Clone the repo:
```
$ git clone https://bitbucket.org/torunar/transmogrify.git
```

Go to the tool folder:
```
$ cd transmogrify
```

Install dependencies via composer:
```
$ composer install
```

Run the tool:
```
$ php ./bin/transmogrify dbHost dbUser dbPassword dbName dbPrefix apiKey [discourseAddress [forumIds [topicsLimit [postsLimit]]]]
```

Arguments:

* `dbHost` — database host of your InVision forum.
* `dbUser` — database user.
* `dbPassword` — database password (`""` or `''` must be specified for the empty password).
* `dbName` — database name.
* `dbPrefix` — table prefix (`""` or `''` must be specified for the empty prefix).
* `apiKey` — API key for the Discourse forum.
* `discourseAddress` — network address of the Discourse forum (default: `http://localhost:4000`).
* `forumIds` — comma-separated list of forum IDs to transfer (e.g. `10,12,13,25,42`).
* `topicsLimit` — max amount of topics per forum to transfer.
* `postsLimit` — max amount of posts per topic to transfer.
