<?php
/*
--- تم تطوير هذا الملف بواسطة المطور @ELSFAH111 ---
--- قناة المطور: @elsfahelmsry ---
*/

$API_KEY  = "8258594683:AAFEKn1F-DgL4quQGWNziNV7m4Uq86mDdds";
$ADMIN_ID = "5060106964";
$API_URL  = "https://api.telegram.org/bot$API_KEY/";

if (!file_exists('data')) {
    mkdir('data', 0777, true);
}
$files_to_create = [
    'data/users.json' => [],
    'data/groups.json' => [],
    'data/admins.json' => [],
    'data/banned.json' => [],
    'data/blocked_words.json' => [],
    'data/replies.json' => [],
    'data/api_keys.json' => [],
    'data/user_states.json' => [],
    'data/last_message_time.json' => [],
    'data/settings.json' => [
        'bot_status' => 'on',
        'join_notify' => 'off',
        'api_status' => 'off',
        'primary_api' => 'gemini',
        'gemini_model' => 'gemini-1.5-flash',
        'chatgpt_model' => 'gpt-4o',
        'developer_name' => 'المطور',
        'bot_name' => 'بوت الذكاء الاصطناعي',
        'bot_description' => 'أنا مساعد ذكاء اصطناعي، تم تطويري للمساعدة والإجابة على استفساراتك.',
        'message_delay' => 5
    ],
    'data/start_message.txt' => 'مرحباً %usernamelink% — اسألني وأنا أجيب 🧠'
];
foreach ($files_to_create as $file => $default_content) {
    if (!file_exists($file)) {
        $content = is_string($default_content) ? $default_content : json_encode($default_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($file, $content);
    }
}

function readData($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}
function writeData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function apiRequest($method, $data = []) {
    global $API_URL;
    $url = $API_URL . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        error_log("CURL ERROR: " . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($res, true);
}
function sendMessage($chat_id, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => "HTML",
        'disable_web_page_preview' => true
    ];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);
    return apiRequest("sendMessage", $data);
}
function editMessage($chat_id, $message_id, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => "HTML",
        'disable_web_page_preview' => true
    ];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);
    return apiRequest("editMessageText", $data);
}
function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest("forwardMessage", [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}
function deleteMessage($chat_id, $message_id) {
    return apiRequest("deleteMessage", [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function getFileDataAsBase64($file_id) {
    global $API_KEY;
    $file_info_res = apiRequest('getFile', ['file_id' => $file_id]);
    if (!$file_info_res || !($file_info_res['ok'] ?? false)) {
        return null;
    }

    $file_path = $file_info_res['result']['file_path'];
    $file_url = "https://api.telegram.org/file/bot{$API_KEY}/{$file_path}";

    $file_content = file_get_contents($file_url);
    if ($file_content === false) {
        return null;
    }

    $mime_type = 'image/jpeg';
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if ($extension === 'png') {
        $mime_type = 'image/png';
    } elseif ($extension === 'webp') {
        $mime_type = 'image/webp';
    } elseif ($extension === 'gif') {
        $mime_type = 'image/gif';
    }

    $base64_data = base64_encode($file_content);

    return ['data' => $base64_data, 'mime' => $mime_type];
}

function callGeminiAPI($prompt, $base64Image = null, $mimeType = null) {
    $keys = readData('data/api_keys.json');
    $settings = readData('data/settings.json');
    $apiKey = $keys['gemini'] ?? null;
    $model = $settings['gemini_model'] ?? 'gemini-1.5-flash';
    if (!$apiKey) return "خطأ: مفتاح Gemini API غير موجود.";

    $description = $settings['bot_description'] ?? '';
    $bot_name = $settings['bot_name'] ?? '';
    $dev_name = $settings['developer_name'] ?? '';
    $final_prompt = "أنت {$bot_name}. {$description}. مطورك هو {$dev_name}. مهمتك هي الإجابة على السؤال التالي:\n\n{$prompt}";
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
    
    $parts = [['text' => $final_prompt]];
    if ($base64Image && $mimeType) {
        $parts[] = ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]];
    }
    $data = ['contents' => [['parts' => $parts]]];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    } else {
        $error_message = $result['error']['message'] ?? json_encode($result, JSON_UNESCAPED_UNICODE);
        return "حدث خطأ أثناء معالجة طلبك عبر Gemini:\n<code>" . htmlspecialchars($error_message) . "</code>";
    }
}
function callChatGPTAPI($prompt, $base64Image = null, $mimeType = null) {
    $keys = readData('data/api_keys.json');
    $settings = readData('data/settings.json');
    $apiKey = $keys['chatgpt'] ?? null;
    $model = $settings['chatgpt_model'] ?? 'gpt-4o';
    if (!$apiKey) return "خطأ: مفتاح ChatGPT API غير موجود.";

    $description = $settings['bot_description'] ?? '';
    $bot_name = $settings['bot_name'] ?? '';
    $dev_name = $settings['developer_name'] ?? '';
    $system_message = "أنت {$bot_name}. {$description}. مطورك هو {$dev_name}.";
    $url = "https://api.openai.com/v1/chat/completions";

    $user_content = [['type' => 'text', 'text' => $prompt]];
    if ($base64Image && $mimeType && in_array($model, ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'])) {
        $user_content[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$base64Image}"]];
    }
    
    $data = [ 
        "model" => $model, 
        "messages" => [ 
            ["role" => "system", "content" => $system_message], 
            ["role" => "user", "content" => $user_content] 
        ] 
    ];

    $headers = ["Content-Type: application/json", "Authorization: Bearer " . $apiKey];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    } else {
        $error_message = $result['error']['message'] ?? json_encode($result, JSON_UNESCAPED_UNICODE);
        return "حدث خطأ أثناء معالجة طلبك عبر ChatGPT:\n<code>" . htmlspecialchars($error_message) . "</code>";
    }
}

function getUserState($user_id) {
    $states = readData('data/user_states.json');
    return $states[$user_id] ?? null;
}
function setUserState($user_id, $state) {
    $states = readData('data/user_states.json');
    $states[$user_id] = $state;
    writeData('data/user_states.json', $states);
}
function clearUserState($user_id) {
    $states = readData('data/user_states.json');
    if (isset($states[$user_id])) {
        unset($states[$user_id]);
        writeData('data/user_states.json', $states);
    }
}

function processText($text, $message) {
    $user = $message['from'] ?? [];
    $first = htmlspecialchars($user['first_name'] ?? '');
    $username_at = isset($user['username']) ? '@' . $user['username'] : 'لا يوجد';
    $user_link = "<a href='tg://user?id=" . ($user['id'] ?? '') . "'>" . $first . "</a>";
    $replacements = [
        '%username%' => $first,
        '%@username%' => $username_at,
        '%usernamelink%' => $user_link,
        '%userlink%' => "tg://user?id=" . ($user['id'] ?? ''),
        '%id%' => $user['id'] ?? '',
        '%date%' => date('Y-m-d'),
        '%time%' => date('H:i:s'),
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $text);
}

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

$callback_query = $update['callback_query'] ?? null;
$message = $update['message'] ?? $callback_query['message'] ?? null;
if (!$message) exit;

$chat_id = $message['chat']['id'];
$user_id = $callback_query['from']['id'] ?? $message['from']['id'];
$text = $message['text'] ?? null;
$data = $callback_query['data'] ?? null;

$admins = readData('data/admins.json');
if (!in_array($ADMIN_ID, $admins)) {
    $admins[] = (string)$ADMIN_ID;
    writeData('data/admins.json', $admins);
}
$isAdmin = in_array((string)$user_id, $admins);

$settings = readData('data/settings.json');
if (($settings['bot_status'] ?? 'on') == 'off' && !$isAdmin) {
    exit;
}

$banned_users = readData('data/banned.json');
if (in_array((string)$user_id, $banned_users)) {
    exit;
}

$chat_type = $message['chat']['type'] ?? 'private';
if ($chat_type == 'private') {
    $users = readData('data/users.json');
    if (!in_array($chat_id, $users)) {
        $users[] = $chat_id;
        writeData('data/users.json', $users);
        if (($settings['join_notify'] ?? 'off') == 'on') {
            $info = "👤 دخول عضو جديد:\n<b>الاسم:</b> " . htmlspecialchars($message["from"]["first_name"] ?? '') .
                    "\n<b>اليوزر:</b> " . (isset($message["from"]["username"]) ? "@" . $message["from"]["username"] : "لا يوجد") .
                    "\n<b>الايدي:</b> <code>{$user_id}</code>\n<b>إجمالي المستخدمين:</b> " . count($users);
            foreach ($admins as $admin) sendMessage($admin, $info);
        }
    }
} elseif ($chat_type == 'group' || $chat_type == 'supergroup') {
    $groups = readData('data/groups.json');
    if (!in_array($chat_id, $groups)) {
        $groups[] = $chat_id;
        writeData('data/groups.json', $groups);
    }
}
/*
--- تم تطوير هذا الملف بواسطة المطور @ELSFAH111 ---
--- قناة المطور: @elsfahelmsry ---
*/
if ($text) {
    $blocked_words = readData('data/blocked_words.json');
    foreach ($blocked_words as $word) {
        if ($word !== '' && mb_stripos($text, $word) !== false) {
            sendMessage($chat_id, processText("⚠️ الكلمة ( $word ) غير مسموح بها يا %username%", $message));
            exit;
        }
    }
}

function showAdminPanel($chat_id, $message_id = null) {
    $settings = readData('data/settings.json');
    $bot_status_icon = ($settings['bot_status'] ?? 'on') == 'on' ? '✅' : '❌';
    $join_notify_icon = ($settings['join_notify'] ?? 'off') == 'on' ? '✅' : '❌';

    $text = "⚙️ أهلاً بك في لوحة التحكم الخاصة بالبوت.";
    $keyboard = ["inline_keyboard" => [
        [["text" => "📊 الإحصائيات", "callback_data" => "stats_main"], ["text" => "📎 الإذاعة (النشر)", "callback_data" => "publish"]],
        [["text" => "👥 المشرفين", "callback_data" => "admins_menu"], ["text" => "🚫 الكلمات المحظورة", "callback_data" => "blocked_words_menu"]],
        [["text" => "✏️ الردود التلقائية", "callback_data" => "replies_menu"], ["text" => "🚫 قائمة الحظر", "callback_data" => "ban_menu"]],
        [["text" => "🔑 إعدادات API", "callback_data" => "api_keys_menu"], ["text" => "✏️ تغيير رسالة /start", "callback_data" => "change_start"]],
        [["text" => "🔔 إشعارات الدخول " . $join_notify_icon, "callback_data" => "toggle_join_notify"], ["text" => "🤖 حالة البوت " . $bot_status_icon, "callback_data" => "toggle_bot_status"]],
        [["text" => "👤 شخصية البوت", "callback_data" => "bot_identity_menu"]]
    ]];
    if ($message_id) editMessage($chat_id, $message_id, $text, $keyboard);
    else sendMessage($chat_id, $text, $keyboard);
}

$user_state = getUserState($user_id);
if ($user_state && $isAdmin && ($text || isset($message['message_id']))) {
    $parts = explode('|', $user_state);
    $action = $parts[0];

    if (!in_array($action, ['pub_forward_wait', 'pub_groups_wait'])) {
        clearUserState($user_id);
    }
    
    switch ($action) {
        case 'set_start_message':
            if ($text) {
                file_put_contents('data/start_message.txt', $text);
                sendMessage($chat_id, "✅ تم تغيير رسالة /start بنجاح.");
            }
            break;

        case 'set_developer_name':
            if($text) {
                $settings = readData('data/settings.json');
                $settings['developer_name'] = $text;
                writeData('data/settings.json', $settings);
                sendMessage($chat_id, "✅ تم تعيين اسم المطور إلى: " . htmlspecialchars($text));
            }
            break;
        case 'set_bot_name':
            if($text) {
                $settings = readData('data/settings.json');
                $settings['bot_name'] = $text;
                writeData('data/settings.json', $settings);
                sendMessage($chat_id, "✅ تم تعيين اسم البوت إلى: " . htmlspecialchars($text));
            }
            break;
        case 'set_bot_description':
            if($text) {
                $settings = readData('data/settings.json');
                $settings['bot_description'] = $text;
                writeData('data/settings.json', $settings);
                sendMessage($chat_id, "✅ تم تعيين وصف البوت بنجاح.");
            }
            break;
        case 'set_message_delay':
            if (is_numeric($text) && $text >= 0) {
                $settings = readData('data/settings.json');
                $settings['message_delay'] = (int)$text;
                writeData('data/settings.json', $settings);
                sendMessage($chat_id, "✅ تم تعيين مدة الانتظار إلى {$text} ثانية.");
            } else {
                sendMessage($chat_id, "❌ خطأ: الرجاء إرسال رقم صالح (0 أو أكبر).");
            }
            break;
          
        case 'add_admin':
            if (is_numeric($text)) {
                $admins = readData('data/admins.json');
                if (!in_array($text, $admins)) {
                    $admins[] = $text;
                    writeData('data/admins.json', $admins);
                    sendMessage($chat_id, "✅ تم إضافة المشرف الجديد بنجاح.");
                } else sendMessage($chat_id, "❌ المشرف موجود بالفعل.");
            } else sendMessage($chat_id, "❌ أرسل ID صالح (أرقام فقط).");
            break;

        case 'remove_admin':
            if($text){
                $admins = readData('data/admins.json');
                if (($k = array_search($text, $admins)) !== false && $text != $GLOBALS['ADMIN_ID']) {
                    unset($admins[$k]);
                    writeData('data/admins.json', array_values($admins));
                    sendMessage($chat_id, "✅ تم إزالة المشرف.");
                } else sendMessage($chat_id, "❌ لا يمكن إزالة هذا المشرف أو أنه غير موجود.");
            }
            break;

        case 'add_blocked_word':
            if($text){
                $words = readData('data/blocked_words.json');
                $words[] = $text;
                writeData('data/blocked_words.json', array_values(array_unique($words)));
                sendMessage($chat_id, "✅ تم إضافة الكلمة للقائمة المحظورة.");
            }
            break;
        case 'remove_blocked_word':
            if($text){
                $words = readData('data/blocked_words.json');
                if (($k = array_search($text, $words)) !== false) {
                    unset($words[$k]);
                    writeData('data/blocked_words.json', array_values($words));
                    sendMessage($chat_id, "✅ تم إزالة الكلمة.");
                } else sendMessage($chat_id, "❌ الكلمة غير موجودة.");
            }
            break;

        case 'add_reply_word':
            if($text){
                setUserState($user_id, 'add_reply_response|' . $text);
                sendMessage($chat_id, "📝 الآن أرسل نص الرد للعبارة: <b>" . htmlspecialchars($text) . "</b>");
            }
            break;

        case 'add_reply_response':
            if($text){
                $state_extra = $parts[1] ?? '';
                $replies = readData('data/replies.json');
                if ($state_extra != '') {
                    $replies[$state_extra] = $text;
                    writeData('data/replies.json', $replies);
                    sendMessage($chat_id, "✅ تم إضافة الرد بنجاح.");
                } else sendMessage($chat_id, "❌ حدث خطأ.");
            }
            break;

        case 'remove_reply':
            if($text){
                $replies = readData('data/replies.json');
                if (isset($replies[$text])) {
                    unset($replies[$text]);
                    writeData('data/replies.json', $replies);
                    sendMessage($chat_id, "✅ تم حذف الرد.");
                } else sendMessage($chat_id, "❌ الرد غير موجود.");
            }
            break;

        case 'set_gemini_api':
            if($text){
                $keys = readData('data/api_keys.json');
                $keys['gemini'] = trim($text);
                writeData('data/api_keys.json', $keys);
                sendMessage($chat_id, "✅ تم حفظ مفتاح Gemini API.");
            }
            break;
        case 'set_chatgpt_api':
            if($text){
                $keys = readData('data/api_keys.json');
                $keys['chatgpt'] = trim($text);
                writeData('data/api_keys.json', $keys);
                sendMessage($chat_id, "✅ تم حفظ مفتاح ChatGPT API.");
            }
            break;

        case 'ban_user':
            if (is_numeric($text)) {
                $b = readData('data/banned.json');
                if (!in_array($text, $b)) {
                    $b[] = $text;
                    writeData('data/banned.json', array_values(array_unique($b)));
                    sendMessage($chat_id, "✅ تم حظر المستخدم {$text}.");
                } else sendMessage($chat_id, "⚠️ المستخدم محظور مسبقاً.");
            } else sendMessage($chat_id, "❌ أرسل ID صالح.");
            break;
        case 'unban_user':
            if($text){
                $b = readData('data/banned.json');
                if (($k = array_search($text, $b)) !== false) {
                    unset($b[$k]);
                    writeData('data/banned.json', array_values($b));
                    sendMessage($chat_id, "✅ تم إلغاء حظر المستخدم.");
                } else sendMessage($chat_id, "❌ المستخدم غير محظور.");
            }
            break;

        case 'pub_normal_wait':
            if($text){
                $users = readData('data/users.json');
                $count = 0;
                $status_msg = sendMessage($chat_id, "⏳ جاري الإرسال إلى " . count($users) . " مستخدم...");
                foreach ($users as $u) {
                    $sent = sendMessage($u, $text); 
                    if ($sent && ($sent['ok'] ?? false)) $count++;
                    usleep(100000); 
                }
                editMessage($chat_id, $status_msg['result']['message_id'], "✅ تم إرسال الرسالة بنجاح إلى {$count} مستخدم(ين).");
            }
            break;

        case 'pub_forward_wait':
             if (isset($message['message_id'])) {
                clearUserState($user_id);
                $users = readData('data/users.json');
                $count = 0;
                $status_msg = sendMessage($chat_id, "⏳ جاري التوجيه إلى " . count($users) . " مستخدم...");
                foreach ($users as $u) {
                    $f = forwardMessage($u, $chat_id, $message['message_id']);
                    if ($f && ($f['ok'] ?? false)) $count++;
                     usleep(100000);
                }
                editMessage($chat_id, $status_msg['result']['message_id'], "✅ تم توجيه الرسالة بنجاح إلى {$count} مستخدم(ين).");
            } else {
                sendMessage($chat_id, "❌ لم يتم استلام رسالة للتوجيه. أعد توجيه الرسالة الآن.");
            }
            break;

        case 'pub_groups_wait':
            if (isset($message['message_id'])) {
                clearUserState($user_id);
                $groups = readData('data/groups.json');
                $count = 0;
                $status_msg = sendMessage($chat_id, "⏳ جاري التوجيه إلى " . count($groups) . " مجموعة...");
                foreach ($groups as $g) {
                    $f = forwardMessage($g, $chat_id, $message['message_id']);
                    if ($f && ($f['ok'] ?? false)) $count++;
                    usleep(100000);
                }
                editMessage($chat_id, $status_msg['result']['message_id'], "✅ تم توجيه الرسالة بنجاح إلى {$count} مجموعة.");
            } else {
                sendMessage($chat_id, "❌ لم يتم استلام رسالة للتوجيه. أعد توجيه الرسالة الآن.");
            }
            break;
        
        default:
            clearUserState($user_id);
            sendMessage($chat_id, "❌ حالة غير معروفة. تم الإلغاء.");
            break;
    }
    exit;
}

if ($text) {
    if ($text == "/start") {
        $start_msg = file_get_contents('data/start_message.txt');
        sendMessage($chat_id, processText($start_msg, $message));
        exit;
    }
    if ($text == "/admin" && $isAdmin) {
        showAdminPanel($chat_id);
        exit;
    }

    $replies = readData('data/replies.json');
    if (isset($replies[$text])) {
        sendMessage($chat_id, processText($replies[$text], $message));
        exit;
    }
}

if ($data && $isAdmin) {
    $message_id = $message['message_id'];
    apiRequest("answerCallbackQuery", ["callback_query_id" => $callback_query["id"]]);
    $backRow = [["text" => "🔙 رجوع للقائمة الرئيسية", "callback_data" => "back_to_main"]];

    if (strpos($data, 'set_gemini_model|') === 0) {
        $model = explode('|', $data)[1];
        $settings = readData('data/settings.json');
        $settings['gemini_model'] = $model;
        writeData('data/settings.json', $settings);
        apiRequest("answerCallbackQuery", ["callback_query_id" => $callback_query["id"], "text" => "✅ تم اختيار موديل Gemini: $model", "show_alert" => true]);
        $data = 'api_keys_menu';
    } elseif (strpos($data, 'set_chatgpt_model|') === 0) {
        $model = explode('|', $data)[1];
        $settings = readData('data/settings.json');
        $settings['chatgpt_model'] = $model;
        writeData('data/settings.json', $settings);
        apiRequest("answerCallbackQuery", ["callback_query_id" => $callback_query["id"], "text" => "✅ تم اختيار موديل ChatGPT: $model", "show_alert" => true]);
        $data = 'api_keys_menu';
    }
/*
--- تم تطوير هذا الملف بواسطة المطور @ELSFAH111 ---
--- قناة المطور: @elsfahelmsry ---
*/
    switch ($data) {
        case 'back_to_main':
            showAdminPanel($chat_id, $message_id);
            break;
        
        case 'bot_identity_menu':
            $settings = readData('data/settings.json');
            $dev_name = htmlspecialchars($settings['developer_name'] ?? 'غير محدد');
            $bot_name = htmlspecialchars($settings['bot_name'] ?? 'غير محدد');
            $bot_desc = htmlspecialchars($settings['bot_description'] ?? 'غير محدد');
            $delay = $settings['message_delay'] ?? 0;
            $text = "<b>👤 إعدادات شخصية البوت</b>\n\n" .
                    "<b>اسم المطور:</b> <code>{$dev_name}</code>\n" .
                    "<b>اسم البوت:</b> <code>{$bot_name}</code>\n" .
                    "<b>وصف/شخصية البوت:</b> <code>{$bot_desc}</code>\n" .
                    "<b>مدة الانتظار:</b> <code>{$delay} ثانية</code>";
            $rows = [
                [["text" => "✏️ تعديل اسم المطور", "callback_data" => "set_dev_name"]],
                [["text" => "🤖 تعديل اسم البوت", "callback_data" => "set_bot_name"]],
                [["text" => "📜 تعديل وصف البوت", "callback_data" => "set_bot_desc"]],
                [["text" => "⏳ تعديل مدة الانتظار", "callback_data" => "set_delay"]]
            ];
            editMessage($chat_id, $message_id, $text, ["inline_keyboard" => array_merge($rows, [$backRow])]);
            break;

        case 'set_dev_name':
            setUserState($user_id, 'set_developer_name');
            editMessage($chat_id, $message_id, "أرسل اسم المطور الجديد.");
            break;
        case 'set_bot_name':
            setUserState($user_id, 'set_bot_name');
            editMessage($chat_id, $message_id, "أرسل اسم البوت الجديد.");
            break;
        case 'set_bot_desc':
            setUserState($user_id, 'set_bot_description');
            editMessage($chat_id, $message_id, "أرسل الوصف أو الشخصية الجديدة للبوت (مثال: أنت مساعد خبير في مجال البرمجة وتتحدث بأسلوب رسمي).");
            break;
        case 'set_delay':
            setUserState($user_id, 'set_message_delay');
            editMessage($chat_id, $message_id, "أرسل مدة الانتظار بالثواني بين كل رسالة (أرسل 0 لإلغاء الانتظار).");
            break;
          
        case 'stats_main':
            $users_count = count(readData('data/users.json'));
            $groups_count = count(readData('data/groups.json'));
            $stats_text = "📊 <b>إحصائيات البوت:</b>\n\n👥 عدد المستخدمين: {$users_count}\n🏢 عدد المجموعات: {$groups_count}\n\n🕒 التاريخ: " . date('Y-m-d H:i');
            editMessage($chat_id, $message_id, $stats_text, ["inline_keyboard" => [$backRow]]);
            break;

        case 'publish':
            $rows = [
                [["text" => "✍️ نشر عادي للمستخدمين (نص)", "callback_data" => "pub_normal"]],
                [["text" => "↪️ نشر بالتوجيه للمستخدمين", "callback_data" => "pub_forward"]],
                [["text" => "🏢 نشر في المجموعات", "callback_data" => "pub_groups"]]
            ];
            editMessage($chat_id, $message_id, "اختر نوع النشر:", ["inline_keyboard" => array_merge($rows, [$backRow])]);
            break;

        case 'pub_normal':
            setUserState($user_id, 'pub_normal_wait');
            editMessage($chat_id, $message_id, "✍️ ارسل الآن نص الرسالة التي تريد نشرها للمستخدمين.");
            break;
        case 'pub_forward':
            setUserState($user_id, 'pub_forward_wait');
            editMessage($chat_id, $message_id, "🔁 أعد توجيه (forward) الرسالة هنا التي تريد نشرها للمستخدمين.");
            break;
        case 'pub_groups':
            setUserState($user_id, 'pub_groups_wait');
            editMessage($chat_id, $message_id, "🔁 أعد توجيه الرسالة هنا التي تريد إرسالها للمجموعات.");
            break;

        case 'admins_menu':
            $admins_list = readData('data/admins.json');
            $list = "";
            foreach ($admins_list as $a) $list .= "- <a href='tg://user?id={$a}'>{$a}</a>" . ($a == $GLOBALS['ADMIN_ID'] ? " (المالك)" : "") . "\n";
            $text_admins = "<b>👥 قائمة المشرفين (" . count($admins_list) . ")</b>\n\n{$list}";
            $rows = [[["text" => "➕ إضافة مشرف", "callback_data" => "add_admin"], ["text" => "➖ إزالة مشرف", "callback_data" => "remove_admin"]]];
            editMessage($chat_id, $message_id, $text_admins, ["inline_keyboard" => array_merge($rows, [$backRow])]);
            break;
        case 'add_admin':
            setUserState($user_id, 'add_admin');
            editMessage($chat_id, $message_id, "أرسل ID المشرف الجديد (أرقام فقط).");
            break;
        case 'remove_admin':
            setUserState($user_id, 'remove_admin');
            editMessage($chat_id, $message_id, "أرسل ID المشرف الذي تريد إزالته.");
            break;

        case 'blocked_words_menu':
            $words = readData('data/blocked_words.json');
            $list = !empty($words) ? "<code>" . implode("\n", array_map('htmlspecialchars', $words)) . "</code>" : "لا توجد كلمات محظورة.";
            $text_bw = "<b>🚫 الكلمات المحظورة حالياً:</b>\n\n{$list}";
            $rows = [[["text" => "➕ إضافة كلمة", "callback_data" => "add_word"], ["text" => "➖ إزالة كلمة", "callback_data" => "remove_word"]]];
            editMessage($chat_id, $message_id, $text_bw, ["inline_keyboard" => array_merge($rows, [$backRow])]);
            break;
        case 'add_word':
            setUserState($user_id, 'add_blocked_word');
            editMessage($chat_id, $message_id, "أرسل الكلمة التي تريد حظرها.");
            break;
        case 'remove_word':
            setUserState($user_id, 'remove_blocked_word');
            editMessage($chat_id, $message_id, "أرسل الكلمة التي تريد إزالتها من الحظر.");
            break;

        case 'replies_menu':
            $replies = readData('data/replies.json');
            $list = "";
            if (!empty($replies)) {
                foreach ($replies as $k => $v) $list .= "<b>" . htmlspecialchars($k) . "</b>  ->  <code>" . htmlspecialchars(mb_substr($v,0,30)) . "...</code>\n";
            } else $list = "لا توجد ردود مضافة.";
            $text_rep = "<b>✏️ الردود التلقائية:</b>\n\n{$list}";
            $rows = [[["text" => "➕ إضافة رد", "callback_data" => "add_reply"], ["text" => "➖ إزالة رد", "callback_data" => "remove_reply"]]];
            editMessage($chat_id, $message_id, $text_rep, ["inline_keyboard" => array_merge($rows, [$backRow])]);
            break;
        case 'add_reply':
            setUserState($user_id, 'add_reply_word');
            editMessage($chat_id, $message_id, "أرسل الكلمة أو العبارة التي تريد إضافة رد لها.");
            break;
        case 'remove_reply':
            setUserState($user_id, 'remove_reply');
            editMessage($chat_id, $message_id, "أرسل الكلمة أو العبارة التي تريد حذف الرد الخاص بها.");
            break;
              
        case 'api_keys_menu':
            $settings = readData('data/settings.json');
            $keys = readData('data/api_keys.json');
            $api_status_icon = ($settings['api_status'] ?? 'off') == 'on' ? '✅' : '❌';
            $gemini_icon = (($settings['primary_api'] ?? 'gemini') == 'gemini') ? '🔹' : '';
            $chatgpt_icon = (($settings['primary_api'] ?? 'gemini') == 'chatgpt') ? '🔹' : '';
            $gemini_key_status = (!empty($keys['gemini'])) ? "✅" : "❌";
            $chatgpt_key_status = (!empty($keys['chatgpt'])) ? "✅" : "❌";

            $text_api = "<b>🔑 إعدادات API للذكاء الاصطناعي</b>\n\nاختر الإعدادات التي تريد التحكم بها:";
            $rows = [
                [["text" => "🤖 حالة الـ API " . $api_status_icon, "callback_data" => "toggle_api_status"]],
                [["text" => $gemini_icon . " اختيار Gemini", "callback_data" => "set_primary_api_gemini"], ["text" => $chatgpt_icon . " اختيار ChatGPT", "callback_data" => "set_primary_api_chatgpt"]],
                [["text" => "تعديل Gemini API " . $gemini_key_status, "callback_data" => "add_gemini"],["text" => "تعديل ChatGPT API " . $chatgpt_key_status, "callback_data" => "add_chatgpt"]],
                [["text" => "🔄 تغيير موديل Gemini", "callback_data" => "change_gemini_model"],["text" => "🔄 تغيير موديل ChatGPT", "callback_data" => "change_chatgpt_model"]],
                [["text" => "📖 تعليمات الحصول على API", "callback_data" => "api_instructions"]]
            ];
            editMessage($chat_id, $message_id, $text_api, ["inline_keyboard" => array_merge($rows, [$backRow])]);
            break;

        case 'change_gemini_model':
            $current_model = readData('data/settings.json')['gemini_model'] ?? 'N/A';
            $text = "<b>اختر موديل Gemini:</b>\n\nالحالي: <code>$current_model</code>\n\n<i>*تم اختيار أقوى الموديلات الرسمية والمستقرة لضمان أفضل أداء.</i>";
            
            $models = [ 'gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-pro', 'gemini-pro-vision' ];
            
            $keyboard = [];
            foreach ($models as $model) {
                 $keyboard[] = [["text" => ($model == $current_model ? '🔹 ' : '') . $model, "callback_data" => "set_gemini_model|$model"]];
            }
            $keyboard[] = [["text" => "🔙 رجوع لقائمة API", "callback_data" => "api_keys_menu"]];
            editMessage($chat_id, $message_id, $text, ["inline_keyboard" => $keyboard]);
            break;

        case 'change_chatgpt_model':
            $current_model = readData('data/settings.json')['chatgpt_model'] ?? 'N/A';
            $text = "<b>اختر موديل ChatGPT:</b>\n\nالحالي: <code>$current_model</code>\n\n<i>*تم اختيار أقوى الموديلات الرسمية والمستقرة لضمان أفضل أداء.</i>";
            
            $models = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'];

            $keyboard = [];
            foreach ($models as $model) {
                 $keyboard[] = [["text" => ($model == $current_model ? '🔹 ' : '') . $model, "callback_data" => "set_chatgpt_model|$model"]];
            }
            $keyboard[] = [["text" => "🔙 رجوع لقائمة API", "callback_data" => "api_keys_menu"]];
            editMessage($chat_id, $message_id, $text, ["inline_keyboard" => $keyboard]);
            break;

        case 'toggle_api_status':
        case 'set_primary_api_gemini':
        case 'set_primary_api_chatgpt':
            $settings = readData('data/settings.json');
            if ($data == 'toggle_api_status') {
                $settings['api_status'] = ($settings['api_status'] ?? 'off') == 'on' ? 'off' : 'on';
            } else {
                $settings['primary_api'] = ($data == 'set_primary_api_gemini') ? 'gemini' : 'chatgpt';
            }
            writeData('data/settings.json', $settings);
            $data = 'api_keys_menu'; 
        
        case 'api_keys_menu': 
            $settings = readData('data/settings.json');
            $keys = readData('data/api_keys.json');
            $api_status_icon = ($settings['api_status'] ?? 'off') == 'on' ? '✅' : '❌';
            $gemini_icon = (($settings['primary_api'] ?? 'gemini') == 'gemini') ? '🔹' : '';
            $chatgpt_icon = (($settings['primary_api'] ?? 'gemini') == 'chatgpt') ? '🔹' : '';
            $gemini_key_status = (!empty($keys['gemini'])) ? "✅" : "❌";
            $chatgpt_key_status = (!empty($keys['chatgpt'])) ? "✅" : "❌";
            $text_api = "<b>🔑 إعدادات API للذكاء الاصطناعي</b>\n\nاختر الإعدادات التي تريد التحكم بها:";
            $rows = [
                [["text" => "🤖 حالة الـ API " . $api_status_icon, "callback_data" => "toggle_api_status"]],
                [["text" => $gemini_icon . " اختيار Gemini", "callback_data" => "set_primary_api_gemini"], ["text" => $chatgpt_icon . " اختيار ChatGPT", "callback_data" => "set_primary_api_chatgpt"]],
                [["text" => "تعديل Gemini API " . $gemini_key_status, "callback_data" => "add_gemini"],["text" => "تعديل ChatGPT API " . $chatgpt_key_status, "callback_data" => "add_chatgpt"]],
                [["text" => "🔄 تغيير موديل Gemini", "callback_data" => "change_gemini_model"],["text" => "🔄 تغيير موديل ChatGPT", "callback_data" => "change_chatgpt_model"]],
                [["text" => "📖 تعليمات الحصول على API", "callback_data" => "api_instructions"]]
            ];
            editMessage($chat_id, $message_id, $text_api, ["inline_keyboard" => array_merge($rows, [$backRow])]);
            break;
            
        case 'api_instructions':
            $instructions = "<b>📖 كيفية الحصول على مفاتيح API</b>\n\nللحصول على مفتاح Gemini API، اتبع الخطوات التالية:\n1. اذهب إلى Google AI Studio.\n2. سجل الدخول بحساب جوجل الخاص بك.\n3. اضغط على 'Get API key'.\n\nللحصول على مفتاح ChatGPT API:\n1. اذهب إلى موقع OpenAI.\n2. اذهب إلى قسم API keys في حسابك.\n3. أنشئ مفتاحًا جديدًا.";
            editMessage($chat_id, $message_id, $instructions, ["inline_keyboard" => [[["text" => "🔙 رجوع لقائمة API", "callback_data" => "api_keys_menu"]]]]);
            break;

        case 'add_gemini':
            setUserState($user_id, 'set_gemini_api');
            editMessage($chat_id, $message_id, "أرسل مفتاح Gemini API الخاص بك.");
            break;
        case 'add_chatgpt':
            setUserState($user_id, 'set_chatgpt_api');
            editMessage($chat_id, $message_id, "أرسل مفتاح ChatGPT API الخاص بك.");
            break;

        case 'ban_menu':
            $b = readData('data/banned.json');
            $list = !empty($b) ? "<code>" . implode("\n", $b) . "</code>" : "لا يوجد مستخدمون محظورون.";
            $text_ban = "<b>🚫 قائمة المحظورين:</b>\n\n{$list}";
            $rows = [[["text" => "🚫 حظر مستخدم", "callback_data" => "ban_user"], ["text" => "✅ إلغاء حظر", "callback_data" => "unban_user"]]];
            editMessage($chat_id, $message_id, $text_ban, ["inline_keyboard" => array_merge($rows, [$backRow])]);
            break;
        case 'ban_user':
            setUserState($user_id, 'ban_user');
            editMessage($chat_id, $message_id, "أرسل ID المستخدم الذي تريد حظره.");
            break;
        case 'unban_user':
            setUserState($user_id, 'unban_user');
            editMessage($chat_id, $message_id, "أرسل ID المستخدم الذي تريد إلغاء حظره.");
            break;

        case 'toggle_join_notify':
            $settings = readData('data/settings.json');
            $settings['join_notify'] = ($settings['join_notify'] ?? 'off') == 'on' ? 'off' : 'on';
            writeData('data/settings.json', $settings);
            showAdminPanel($chat_id, $message_id);
            break;

        case 'toggle_bot_status':
            $settings = readData('data/settings.json');
            $settings['bot_status'] = ($settings['bot_status'] ?? 'on') == 'on' ? 'off' : 'on';
            writeData('data/settings.json', $settings);
            showAdminPanel($chat_id, $message_id);
            break;

        case 'change_start':
            setUserState($user_id, 'set_start_message');
            $variables_text = "<b>✅ المتغيرات المدعومة:</b>\n\n" .
                              "<code>%username%</code>  - اسم المستخدم\n" .
                              "<code>%@username%</code> - يوزر (إن وجد)\n" .
                              "<code>%usernamelink%</code> - اسم قابل للنقر\n" .
                              "<code>%id%</code> - آي دي المستخدم\n" .
                              "<code>%date%</code> - التاريخ\n" .
                              "<code>%time%</code> - الوقت";
            $prompt_text = "✍️ أرسل الآن رسالة /start الجديدة.\n\n" . $variables_text;
            editMessage($chat_id, $message_id, $prompt_text, ["inline_keyboard" => [$backRow]]);
            break;
        
        default:
            break;
    }
    exit;
}
/*
--- تم تطوير هذا الملف بواسطة المطور @ELSFAH111 ---
--- قناة المطور: @elsfahelmsry ---
*/
$photo = $message['photo'] ?? null;
$prompt = $message['caption'] ?? $message['text'] ?? null;

if (($prompt || $photo) && substr($prompt, 0, 1) !== '/' && !$isAdmin && !$user_state && !$callback_query) {
    $settings = readData('data/settings.json');
    if (($settings['api_status'] ?? 'off') == 'on') {

        $base64Image = null;
        $mimeType = null;
        
        if ($photo) {
            $file_id = end($photo)['file_id']; 
            $imageData = getFileDataAsBase64($file_id);
            if ($imageData) {
                $base64Image = $imageData['data'];
                $mimeType = $imageData['mime'];
                if (empty($prompt)) { 
                    $prompt = "صف هذه الصورة بالتفصيل.";
                }
            } else {
                 sendMessage($chat_id, "❌ حدث خطأ أثناء محاولة تحميل الصورة.");
                 exit;
            }
        }
        
        $delay = $settings['message_delay'] ?? 0;
        if ($delay > 0) {
            $last_message_times = readData('data/last_message_time.json');
            $last_time = $last_message_times[$user_id] ?? 0;
            $current_time = time();

            if ($current_time - $last_time < $delay) {
                $remaining_time = $delay - ($current_time - $last_time);
                $wait_msg = sendMessage($chat_id, "⏳ الرجاء الانتظار قليلاً قبل إرسال رسالة أخرى...");
                if (isset($wait_msg['result']['message_id'])) {
                    sleep($remaining_time);
                    deleteMessage($chat_id, $wait_msg['result']['message_id']);
                } else {
                    sleep($remaining_time);
                }
            }
            $last_message_times[$user_id] = time();
            writeData('data/last_message_time.json', $last_message_times);
        }
        
        $thinking_message = sendMessage($chat_id, "🧠...");
        $message_id_to_edit = $thinking_message['result']['message_id'] ?? null;

        $response_text = "";
        if (($settings['primary_api'] ?? 'gemini') == 'gemini') {
            $response_text = callGeminiAPI($prompt, $base64Image, $mimeType);
        } else {
            $response_text = callChatGPTAPI($prompt, $base64Image, $mimeType);
        }

        if ($message_id_to_edit) {
            editMessage($chat_id, $message_id_to_edit, $response_text);
        } else {
            sendMessage($chat_id, $response_text);
        }
        exit;
    }
}
?>

/*
--- تم تطوير هذا الملف بواسطة المطور @ELSFAH111 ---
--- قناة المطور: @elsfahelmsry ---
*/