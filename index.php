<?php

use InstagramAPI\Instagram;
use Symfony\Component\Dotenv\Dotenv;

require_once 'vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$ig       = new Instagram();
$login    = getenv('LOGIN');
$password = getenv('PASSWORD');

$period = 5 * 60; // 5 minutes => cron task should be set to every five minutes
$finish  = time() + $period;

if (! ($login || $password))
{
    throw new Exception("Add login and pass to .env file.");
}

echo "trying to login..\n";
$ig->login($login, $password);

$userId   = 1121989009; //hardcoded to save api calls
  //$userId = $ig->people->getUserIdForName($username); echo "user id = $userId\n";

$sendTelegram = function ($message = 'hello world') use ($username)
{
    $token  = getenv('TG_BOT_TOKEN');
    $chatId = getenv('TG_BOT_CHAT_ID'); // chat with vastly

    if (! ($token && $chatId))
    {
        return; // tg notifications are not configured
    }

    $telegram = new Telegram($token);
    $content  = ['chat_id' => $chatId, 'text' => "username = $username, " . $message];
    $telegram->sendMessage($content);
};

do
{
    echo "fetching feed....\n";
    $response = $ig->timeline->getUserFeed($userId, null);
    echo "fetched $username feed\n";

    $item      = $response->getItems()[0];
    $likeCount = $item->getLikeCount();
    echo "newest item: {$item->getItemUrl()} id = {$item->getId()} . likes = $likeCount\n";

    if ($item->isHasLiked())
    {
        echo "item was liked already, skipping..\n";
    } else
    {
        echo "liking.. (current likes = $likeCount)\n";
        $response = $ig->media->like($item->getId());
        if ($response->isOk())
        {
            echo "like successful\n";
            $sendTelegram("successful like! existing likes amount = $likeCount");
        } else
        {
            echo "error on like: " . $response->getMessage() . "\n";
        }
    }

    sleep(5); // anti anti abuse protection

} while (time() < $finish);
echo "finished $period seconds cycle\n";
echo "done\n";
