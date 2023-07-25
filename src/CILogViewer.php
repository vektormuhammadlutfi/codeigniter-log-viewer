<?php

namespace CILogViewer;

class CILogViewer
{
    // An array of icons corresponding to different log levels
    private static $levelsIcon = [
        'CRITICAL' => 'glyphicon glyphicon-error-sign',
        'INFO'  => 'glyphicon glyphicon-info-sign',
        'ERROR' => 'glyphicon glyphicon-warning-sign',
        'DEBUG' => 'glyphicon glyphicon-exclamation-sign',
        'ALL'   => 'glyphicon glyphicon-minus',
    ];

    // An array of CSS class names corresponding to different log levels
    private static $levelClasses = [
        'CRITICAL' => 'danger',
        'INFO'  => 'info',
        'ERROR' => 'danger',
        'DEBUG' => 'warning',
        'ALL'   => 'muted',
    ];

    // Regular expression pattern to match the log line header
    const LOG_LINE_HEADER_PATTERN = '/^([A-Z]+)\s*\-\s*([\-\d]+\s+[\:\d]+)\s*\-\->\s*(.+)$/';


    // Path to the log folder on the system
    private $logFolderPath = WRITEPATH . 'logs/';

    // File pattern to pick all log files in the log folder
    private $logFilePattern = "log-*.log";

    // Combination of log folder path and file pattern
    private $fullLogFilePath = "";

    // Name of the view to pass to the renderer
    private $viewName = "logs";

    // Maximum size of a log file (50MB)
    const MAX_LOG_SIZE = 52428800;

    // Maximum length of a log message (300 chars)
    const MAX_STRING_LENGTH = 300;

    // API commands
    private const API_QUERY_PARAM = "api";
    private const API_FILE_QUERY_PARAM = "f";
    private const API_LOG_STYLE_QUERY_PARAM = "sline";
    private const API_CMD_LIST = "list";
    private const API_CMD_VIEW = "view";
    private const API_CMD_DELETE = "delete";


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Bootstrap the library by initializing the configuration.
     *
     * @throws \Exception
     */
    private function init()
    {
        $viewerConfig = config('CILogViewer');

        if ($viewerConfig) {
            if (isset($viewerConfig->viewPath)) {
                $this->viewPath = $viewerConfig->viewPath;
            }
            if (isset($viewerConfig->logFilePattern)) {
                $this->logFilePattern = $viewerConfig->logFilePattern;
            }
        }

        $loggerConfig = config('Logger');

        if (isset($loggerConfig->path)) {
            $this->logFolderPath = $loggerConfig->path;
        }

        $this->fullLogFilePath = $this->logFolderPath . $this->logFilePattern;
    }

    /**
     * Show the logs in the log viewer.
     *
     * @return string The parsed view file content as a string.
     */
    public function showLogs()
    {
        $request = \Config\Services::request();

        if (!is_null($request->getGet("del"))) {
            $this->deleteFiles(base64_decode($request->getGet("del")));
            $uri = \Config\Services::request()->uri->getPath();
            return redirect()->to('/' . $uri);
        }

        $dlFile = $request->getGet("dl");

        if (!is_null($dlFile) && file_exists($this->logFolderPath . basename(base64_decode($dlFile)))) {
            $file = $this->logFolderPath . basename(base64_decode($dlFile));
            $this->downloadFile($file);
        }

        if (!is_null($request->getGet(self::API_QUERY_PARAM))) {
            return $this->processAPIRequests($request->getGet(self::API_QUERY_PARAM));
        }

        $fileName = $request->getGet("f");
        $files = $this->getFiles();

        $currentFile = null;

        if (!is_null($fileName)) {
            $currentFile = $this->logFolderPath . basename(base64_decode($fileName));
        } elseif (is_null($fileName) && !empty($files)) {
            $currentFile = $this->logFolderPath . $files[0];
        }

        $logs = [];

        if (!is_null($currentFile) && file_exists($currentFile)) {
            $fileSize = filesize($currentFile);

            if (is_int($fileSize) && $fileSize > self::MAX_LOG_SIZE) {
                $logs = null;
            } else {
                $logs = $this->processLogs($this->getLogs($currentFile));
            }
        }

        $data['logs'] = $logs;
        $data['files'] = !empty($files) ? $files : [];
        $data['currentFile'] = !is_null($currentFile) ? basename($currentFile) : "";

        return view($this->viewName, $data);
    }

    /**
     * Process the API requests and return JSON responses.
     *
     * @param string $command The API command.
     * @return string JSON response.
     */
    private function processAPIRequests($command)
    {
        $request = \Config\Services::request();
        $response = [];

        if ($command === self::API_CMD_LIST) {
            $response["status"] = true;
            $response["log_files"] = $this->getFilesBase64Encoded();
        } elseif ($command === self::API_CMD_VIEW) {
            $file = $request->getGet(self::API_FILE_QUERY_PARAM);
            $response["log_files"] = $this->getFilesBase64Encoded();

            if (is_null($file) || empty($file)) {
                $response["status"] = false;
                $response["error"]["message"] = "Invalid File Name Supplied: [" . json_encode($file) . "]";
                $response["error"]["code"] = 400;
            } else {
                $singleLine = $request->getGet(self::API_LOG_STYLE_QUERY_PARAM);
                $singleLine = !is_null($singleLine) && ($singleLine === true || $singleLine === "true" || $singleLine === "1") ? true : false;
                $logs = $this->processLogsForAPI($file, $singleLine);
                $response["status"] = true;
                $response["logs"] = $logs;
            }
        } elseif ($command === self::API_CMD_DELETE) {
            $file = $request->getGet(self::API_FILE_QUERY_PARAM);

            if (is_null($file)) {
                $response["status"] = false;
                $response["error"]["message"] = "NULL value is not allowed for file param";
                $response["error"]["code"] = 400;
            } else {
                $fileExists = false;

                if ($file !== "all") {
                    $file = basename(base64_decode($file));
                    $fileExists = file_exists($this->logFolderPath . $file);
                } else {
                    $fileExists = file_exists($this->logFolderPath);
                }

                if ($fileExists) {
                    $this->deleteFiles($file);
                    $response["status"] = true;
                    $response["message"] = "File [" . $file . "] deleted";
                } else {
                    $response["status"] = false;
                    $response["error"]["message"] = "File does not exist";
                    $response["error"]["code"] = 404;
                }
            }
        } else {
            $response["status"] = false;
            $response["error"]["message"] = "Unsupported Query Command [" . $command . "]";
            $response["error"]["code"] = 400;
        }

        // Convert response to JSON and send the appropriate HTTP response code
        header("Content-Type: application/json");
        if (!$response["status"]) {
            http_response_code(400);
        } else {
            http_response_code(200);
        }

        return json_encode($response);
    }

    /**
     * Process the logs by extracting log levels, icons, classes, and message content.
     *
     * @param array $logs The raw logs as read from the log file.
     * @return array|null Processed logs.
     */
    private function processLogs($logs)
    {
        if (is_null($logs)) {
            return null;
        }

        $superLog = [];

        foreach ($logs as $log) {
            $data = [];

            if ($this->getLogHeaderLine($log, $data["level"], $data["date"], $logMessage)) {
                if (strlen($logMessage) > self::MAX_STRING_LENGTH) {
                    $data['content'] = substr($logMessage, 0, self::MAX_STRING_LENGTH);
                    $data["extra"] = substr($logMessage, (self::MAX_STRING_LENGTH + 1));
                } else {
                    $data["content"] = $logMessage;
                }
            } elseif (!empty($superLog)) {
                $prevLog = $superLog[count($superLog) - 1];
                $extra = array_key_exists("extra", $prevLog) ? $prevLog["extra"] : "";
                $prevLog["extra"] = $extra . "<br>" . $log;
                $superLog[count($superLog) - 1] = $prevLog;
            }

            if (!empty($data)) {
                $data["icon"] = self::$levelsIcon[$data["level"]];
                $data["class"] = self::$levelClasses[$data["level"]];
                array_push($superLog, $data);
            }
        }

        return $superLog;
    }

    /**
     * Extract the log level, log date, and log message from a log line using regex.
     *
     * @param string $logLine The log line to extract information from.
     * @param string $level The log level extracted from the log line.
     * @param string $dateTime The log date extracted from the log line.
     * @param string $message The log message extracted from the log line.
     * @return array|bool|null Matches of the regex pattern.
     */
    private function getLogHeaderLine($logLine, &$level, &$dateTime, &$message)
    {
        $matches = [];
        if (preg_match(self::LOG_LINE_HEADER_PATTERN, $logLine, $matches)) {
            $level = $matches[1];
            $dateTime = $matches[2];
            $message = $matches[3];
        }
        return $matches;
    }

    /**
     * Get the content of a log file and return it as an array of lines.
     *
     * @param string $fileName The complete file path of the log file.
     * @return array|null The contents of the log file as an array of lines.
     */
    private function getLogs($fileName)
    {
        $size = filesize($fileName);
        if (!$size || $size > self::MAX_LOG_SIZE) {
            return null;
        }
        return file($fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    /**
     * Get the list of log files in the log folder.
     *
     * @param bool $basename If true, return only the base name of the files.
     * @return array The list of log files.
     */
    private function getFiles($basename = true)
    {
        $files = glob($this->fullLogFilePath);

        $files = array_reverse($files);
        $files = array_filter($files, 'is_file');

        if (is_array($files)) {
            foreach ($files as $k => $file) {
                $files[$k] = basename($file);
            }
        }

        return array_values($files);
    }

    /**
     * Get the list of log files in the log folder with base64-encoded names.
     *
     * @return array The list of log files with base64-encoded names.
     */
    private function getFilesBase64Encoded()
    {
        $files = glob($this->fullLogFilePath);

        $files = array_reverse($files);
        $files = array_filter($files, 'is_file');

        $finalFiles = [];

        foreach ($files as $file) {
            array_push($finalFiles, ["file_b64" => base64_encode(basename($file)), "file_name" => basename($file)]);
        }

        return $finalFiles;
    }

    /**
     * Delete one or more log files in the log folder.
     *
     * @param string $fileName The filename to delete. If "all", delete all log files.
     */
    private function deleteFiles($fileName)
    {
        if ($fileName == "all") {
            array_map("unlink", glob($this->fullLogFilePath));
        } else {
            unlink($this->logFolderPath . basename($fileName));
        }
    }

    /**
     * Prepare the raw file name by decoding it from base64 and appending the log folder path.
     *
     * @param string $fileNameInBase64 The raw file name in base64 format.
     * @return string|null The prepared file name including the log folder path.
     */
    private function prepareRawFileName($fileNameInBase64)
    {
        if (!is_null($fileNameInBase64) && !empty($fileNameInBase64)) {
            return $this->logFolderPath . basename(base64_decode($fileNameInBase64));
        } else {
            return null;
        }
    }

    /**
     * Download a log file to the local disk.
     *
     * @param string $file The complete path of the log file.
     */
    private function downloadFile($file)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
