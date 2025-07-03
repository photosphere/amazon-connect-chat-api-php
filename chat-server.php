<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once 'load-env.php';
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception('Composer dependencies not installed. Run: composer install');
    }
    require_once 'vendor/autoload.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Setup error: ' . $e->getMessage()]);
    exit;
}

use Aws\ConnectParticipant\ConnectParticipantClient;
use Aws\Connect\ConnectClient;

class ChatServer {
    private $connectClient;
    private $participantClient;
    
    public function __construct() {
        $this->connectClient = new ConnectClient([
            'version' => 'latest',
            'region' => 'us-east-1' // 根据需要修改区域
        ]);
    }
    
    public function startChat($contactFlowId, $instanceId, $displayName = 'Customer') {
        try {
            $result = $this->connectClient->startChatContact([
                'ContactFlowId' => $contactFlowId,
                'InstanceId' => $instanceId,
                'ParticipantDetails' => [
                    'DisplayName' => $displayName
                ]
            ]);
            
            $connectionToken = $result['ParticipantToken'];
            
            $this->participantClient = new ConnectParticipantClient([
                'version' => 'latest',
                'region' => 'us-east-1'
            ]);
            
            $connectionResult = $this->participantClient->createParticipantConnection([
                'ParticipantToken' => $connectionToken,
                'Type' => ['WEBSOCKET', 'CONNECTION_CREDENTIALS']
            ]);
            
            // 建立WebSocket连接并发送订阅消息
            $websocketUrl = $connectionResult['Websocket']['Url'];
            $this->establishWebSocketConnection($websocketUrl);
            
            return [
                'success' => true,
                'contactId' => $result['ContactId'],
                'participantId' => $result['ParticipantId'],
                'participantToken' => $connectionToken,
                'websocketUrl' => $websocketUrl,
                'connectionToken' => $connectionResult['ConnectionCredentials']['ConnectionToken']
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function sendMessage($connectionToken, $message) {
        try {
            if (!$this->participantClient) {
                $this->participantClient = new ConnectParticipantClient([
                    'version' => 'latest',
                    'region' => 'us-east-1'
                ]);
            }
            
            $result = $this->participantClient->sendMessage([
                'ConnectionToken' => $connectionToken,
                'Content' => $message,
                'ContentType' => 'text/plain'
            ]);
            
            return ['success' => true, 'messageId' => $result['Id']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getTranscript($connectionToken, $nextToken = null) {
        try {
            if (!$this->participantClient) {
                $this->participantClient = new ConnectParticipantClient([
                    'version' => 'latest',
                    'region' => 'us-east-1'
                ]);
            }
            
            $params = [
                'ConnectionToken' => $connectionToken,
                'MaxResults' => 15,
                'SortOrder' => 'ASCENDING'
            ];
            
            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            }
            
            $result = $this->participantClient->getTranscript($params);
            
            return [
                'success' => true,
                'transcripts' => $result['Transcript'],
                'nextToken' => $result['NextToken'] ?? null
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function disconnectParticipant($connectionToken) {
        try {
            if (!$this->participantClient) {
                $this->participantClient = new ConnectParticipantClient([
                    'version' => 'latest',
                    'region' => 'us-east-1'
                ]);
            }
            
            $this->participantClient->disconnectParticipant([
                'ConnectionToken' => $connectionToken
            ]);
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function establishWebSocketConnection($websocketUrl) {
        $subscribeMessage = json_encode([
            'topic' => 'aws/subscribe',
            'content' => [
                'topics' => ['aws/chat']
            ]
        ]);
        
        error_log('发送订阅消息: ' . $subscribeMessage);
        error_log('WebSocket URL: ' . $websocketUrl);
    }
}

try {
    $chatServer = new ChatServer();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Initialization error: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'start':
        $contactFlowId = $_POST['contactFlowId'];
        $instanceId = $_POST['instanceId'];
        $displayName = $_POST['displayName'] ?? 'Customer';
        echo json_encode($chatServer->startChat($contactFlowId, $instanceId, $displayName));
        break;
        
    case 'send':
        $connectionToken = $_POST['connectionToken'];
        $message = $_POST['message'];
        echo json_encode($chatServer->sendMessage($connectionToken, $message));
        break;
        
    case 'transcript':
    case 'get_transcripts':
        $connectionToken = $_POST['connectionToken'] ?? $_GET['connectionToken'];
        $nextToken = $_POST['nextToken'] ?? $_GET['nextToken'] ?? null;
        echo json_encode($chatServer->getTranscript($connectionToken, $nextToken));
        break;
        
    case 'disconnect':
        $connectionToken = $_POST['connectionToken'];
        echo json_encode($chatServer->disconnectParticipant($connectionToken));
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>