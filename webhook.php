<?php
// ----------------------------------------------------------------------------
// webhook.php - Webex cowpanion message webhook handler
//
// November 2022, Sam Boyer
// ----------------------------------------------------------------------------

ini_set('error_reporting', E_ALL);
ini_set("log_errors", TRUE);
ini_set('error_log', 'errors.log');

// Imports
foreach (glob("CowSay/src/Traits/*.php") as $filename) require_once($filename);
foreach (glob("CowSay/src/Core/*.php") as $filename) require_once($filename);
require_once("CowSay/src/Carcases/Sheep.php");
use CowSay\Sheep;



// == Config ==
$DEBUG_RECIPIENT = 'saboyer@cisco.com';
$DEBUG_MESSAGE_ON_HOOK = false;

// If TEST_MODE is on, only messages sent by the DEBUG_RECIPIENT will be handled.
$TEST_MODE = false;

$BOT_KEY = file_get_contents("BOT_KEY");
$BOT_ID = 'Y2lzY29zcGFyazovL3VzL0FQUExJQ0FUSU9OL2QzODU5ODcxLWQ2YmEtNDliYi05MmMyLWVkYWYxOGRlNTgzYg';
$BOT_EMAIL = 'sheepanion@webex.bot';


//  == Consts ==
$USAGE = "Usage:
say <your_message>
    sheep says your_message to you.
send <recipient_email_address> <your_message>
    sheep says your_message to the given recipient.
adopt
    adopt a sheepanion. this is a large responsibility.
donate <n> <recipient_email_address>
    donate n of your sheep to the given recipient.
";


// == Debug functions ==

function send_debug_message($message_body_markdown) {
    GLOBAL $DEBUG_RECIPIENT;
    return send_message($DEBUG_RECIPIENT, $message_body_markdown);
}

function debug_log_message_info($msg_info) {
    GLOBAL $DEBUG_MESSAGE_ON_HOOK;

    $sender_email = $msg_info->{'personEmail'};
    $msg_text = $msg_info->{'text'};
    $msg_ts = $msg_info->{'created'};
    $dbg_msg = "[DEBUG] $sender_email ($msg_ts): $msg_text\n";

    if ($DEBUG_MESSAGE_ON_HOOK) {
        send_debug_message($dbg_msg);
    }

    $fp = fopen('webhook.log','a');
    fwrite($fp, $dbg_msg);
    fclose($fp);
}



// == Helper functions ==



//  == Message send/recv ==
function send_message($recipient, $message_body_markdown) {
    GLOBAL $BOT_KEY;

    assert(!is_null($message_body_markdown), "empty message");

    $url = 'https://webexapis.com/v1/messages';

    $data = array(
        'toPersonEmail' => $recipient,
        'markdown' => $message_body_markdown
    );

    $headers = array(
        "Authorization: Bearer $BOT_KEY",
        // 'Content-Type: multipart/form-data'
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $curl_response = curl_exec($ch);
    curl_close($ch);

    return json_decode($curl_response);
}

function get_message_info($message_id) {
    GLOBAL $BOT_KEY;

    $url = "https://webexapis.com/v1/messages/$message_id";


    $headers = array(
        "Authorization: Bearer $BOT_KEY",
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $curl_response = curl_exec($ch);
    curl_close($ch);

    return json_decode($curl_response);

}

function send_cow_message($recipient, $cowsay_input) {
    $cow_obj = new Sheep($cowsay_input);
    $cow_out = $cow_obj->say();
    $response = "```c\n$cow_out\n```";

    return send_message($recipient, $response);
}

function send_usage($recipient, $error_string) {
    GLOBAL $USAGE;
    if ($error_string !== "") {
        $msg = "$error_string\n$USAGE";
    } else {
        $msg = $USAGE;
    }

    return send_cow_message($recipient, $msg);
}

function send_error($recipient, $error_string) {
    return send_cow_message($recipient, $error_string);
}

function send_hint($recipient) {
    $cow_obj = new Sheep('baaaaaaaaaaaaa');
    $cow_out = $cow_obj->say();
    $response = "```c\n$cow_out\n(Send 'help' to see commands)\n```";

    return send_message($recipient, $response);
}


// == adoption/trading code ==

function load_sheep_count($owner) {
    // Returns the number of sheep owned by the given owner.
    $num_sheeps = 0;
    $adoption_file = "adoptions/$owner";
    if (file_exists($adoption_file)){
        $f = fopen($adoption_file,"r");
        $num_sheeps = (int)fread($f, filesize($adoption_file));
        fclose($f);
    }
    return $num_sheeps;
}

function write_sheep_count($owner, $num_sheeps) {
    // Writes the number of sheep owned by the given owner.
    $adoption_file = "adoptions/$owner";
    $f = fopen($adoption_file,"w");
    fwrite($f, "$num_sheeps");
    fclose($f);
}


// == Main path ==

$notif_body = json_decode(file_get_contents('php://input'));

if (!is_object($notif_body)) {
    exit();
}

$sender_email = $notif_body->{'data'}->{'personEmail'};

if ($sender_email == $BOT_EMAIL) {
    exit();
}

$msg_info = get_message_info($notif_body->{'data'}->{'id'});
$is_dm = ($msg_info->{'roomType'} === 'direct');
$msg_text = $msg_info->{'text'};
$msg_words = explode(' ',
    str_replace("\n", ' ',
        preg_replace('/ +/',' ', $msg_text)
    )
);

debug_log_message_info($msg_info);

if ($TEST_MODE && $sender_email !== $DEBUG_RECIPIENT) {
    exit();
}


// If the first word is a tag of the bot, strip it
$bot_mentioned = false;
if (property_exists($msg_info, 'mentionedPeople')) {
    foreach ($msg_info->{'mentionedPeople'} as $person_id) {
        if ($person_id === $BOT_ID) {
            $bot_mentioned = true;
        }
    }
}

if ($bot_mentioned && !$is_dm && $msg_words[0] == 'cowpanion') {
    // remove 'cowpanion' from start of message
    $msg_words = array_slice($msg_words, 1);
    $msg_text = preg_replace(
        '/\s*cowpanion\s+/',
        '',
        $msg_text,
        1
    );
}

// Main command handling
if ($bot_mentioned || $is_dm) {
    if ($msg_words[0] === 'say') {
        $message_to_say = preg_replace(
            '/\s*say\s+/',
            '',
            $msg_text,
            1
        );
        send_cow_message($sender_email, $message_to_say);
    }
    elseif ($msg_words[0] === 'send') {
        $cmd_send_recipient = $msg_words[1];

        if (preg_match('/^[\w_.+-]+@[\w-]+\.[\w-.]+$/', $cmd_send_recipient) == 1) {
            $message_to_say = preg_replace(
                '/\s*send\s+[^\s]+\s+/',
                '',
                $msg_text,
                1
            );

            $resp = send_cow_message($cmd_send_recipient, $message_to_say);

            if (property_exists($resp, 'errors')
                && count($resp->{'errors'}) > 0) {
                // If sending message fails, report an error to the user.
                send_message($sender_email, "Error: "
                    . $resp->{'errors'}[0]->{'description'});
            }
            else{
                send_message($sender_email, "Sent!");
            }
        }
        else {
            send_cow_message($sender_email, "Invalid email address '$cmd_send_recipient'");
        }
    }
    elseif ($msg_words[0] === 'help') {
        send_usage($sender_email, '');
    }
    elseif ($msg_words[0] === 'adopt') {
        $num_sheeps = load_sheep_count($sender_email);
        $num_sheeps+=1;
        write_sheep_count($sender_email, $num_sheeps);
        send_message($sender_email, "```\nYou have adopted $num_sheeps sheep 🐑\n```");
    }
    elseif ($msg_words[0] === 'donate') {
        $cmd_num_donate = $msg_words[1];
        $cmd_send_recipient = $msg_words[2];

        $src_num_sheeps = load_sheep_count($sender_email);
        $dest_num_sheeps = load_sheep_count($cmd_send_recipient);


        // input validation
        if ($sender_email == $cmd_send_recipient) {
            send_cow_message($sender_email, "You can't donate sheep to yourself. self-kindness is banned.");
            return;
        }
        if (filter_var($cmd_num_donate, FILTER_VALIDATE_INT) === false || $cmd_num_donate == 0) {
            send_cow_message($sender_email, "Invalid sheep number. Whole numbers of sheep only please.");
            return;
        }
        if (preg_match('/^[\w_.+-]+@[\w-]+\.[\w-.]+$/', $cmd_send_recipient) != 1) {
            send_cow_message($sender_email, "Invalid email address '$cmd_send_recipient'");
            return;
        }
        if ($cmd_num_donate > 0 && $cmd_num_donate > $src_num_sheeps) {
            send_cow_message($sender_email, "You don't have that many sheep to donate!");
            return;
        }
        $neg = -$cmd_num_donate;
        if ($cmd_num_donate < 0 && $neg > $dest_num_sheeps) {
            send_cow_message($sender_email, "'recipient' doesn't have enough sheep to steal.");
            return;
        }

        $dest_num_sheeps+=$cmd_num_donate;
        $src_num_sheeps-=$cmd_num_donate;
        write_sheep_count($sender_email, $src_num_sheeps);
        write_sheep_count($cmd_send_recipient, $dest_num_sheeps);

        if ($cmd_num_donate > 0) {
            send_cow_message($sender_email, "farewell 😔");
            send_message($cmd_send_recipient, "🐑 You have received $cmd_num_donate sheep from an undisclosed recipient 🐑");
        } else {
            $neg = -$cmd_num_donate;
            send_cow_message($sender_email, "You have embarked on a life of crime. your contact details have been forwarded to the sheep police.");
            send_message($cmd_send_recipient, "🐑🏴‍☠️ $neg of your sheep have been stolen by a mysterious figure looking suspiciously like $sender_email 🐑🏴‍☠️");
        }
    }
    else {
        send_hint($sender_email);
    }
}
else {
    send_hint($sender_email);
}

?>