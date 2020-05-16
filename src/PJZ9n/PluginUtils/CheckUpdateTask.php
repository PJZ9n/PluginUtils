<?php

/**
 * Copyright (c) 2020 PJZ9n.
 *
 * This file is part of PluginUtils.
 *
 * PluginUtils is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PluginUtils is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PluginUtils. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace PJZ9n\PluginUtils;

use pocketmine\lang\BaseLang;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use Logger;
use Throwable;

class CheckUpdateTask extends AsyncTask
{
    
    /**
     * @var string Github API
     * Ex: https://api.github.com/repos/<UserName>/<RepoName>/releases/latest
     */
    private $updateHost;
    
    /** @var BaseLang */
    private $lang;
    
    /** @var string */
    private $currentVersion;
    
    /** @var string */
    private $userAgent;
    
    public function __construct(string $updateHost, BaseLang $lang, string $currentVersion, Logger $logger)
    {
        $this->updateHost = $updateHost;
        $this->lang = $lang;
        $this->currentVersion = $currentVersion;
        $server = Server::getInstance();
        $this->userAgent = $server->getName();
        
        $logger->info($lang->translateString("update.check.host", [$this->updateHost]));
        $logger->debug("Useragent: " . $this->userAgent);
        
        $this->storeLocal($logger);
    }
    
    public function onRun(): void
    {
        try {
            $ch = curl_init($this->updateHost);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            $result = curl_exec($ch);
            $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $this->setResult([
                "code" => $responseCode,
                "result" => $result,
            ]);
            curl_close($ch);
        } catch (Throwable $throwable) {
            $this->setResult($throwable);
        }
    }
    
    public function onCompletion(Server $server): void
    {
        /** @var Logger $logger */
        $logger = $this->fetchLocal();
        $result = $this->getResult();
        if ($result instanceof Throwable) {
            $logger->error($this->lang->translateString("update.check.failed.error", ["Exception", $result->getMessage()]));
            $logger->error($this->lang->translateString("update.check.failed"));
            return;
        }
        if ($result["result"] === false) {
            $logger->error($this->lang->translateString("update.check.failed.error", ["cURL", "curl_exec() returned false"]));
            $logger->error($this->lang->translateString("update.check.failed"));
            return;
        }
        if ($result["code"] !== 200) {
            $logger->error($this->lang->translateString("update.check.failed.error", ["HTTP", $result["code"]]));
            $logger->error($this->lang->translateString("update.check.failed"));
            return;
        }
        if (!is_string($result["result"])) {
            $logger->error($this->lang->translateString("update.check.failed.error", ["Response", "Result must be string"]));
            $logger->error($this->lang->translateString("update.check.failed"));
            return;
        }
        $release = json_decode($result["result"], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger->error($this->lang->translateString("update.check.failed.error", ["JSON", json_last_error_msg()]));
            $logger->error($this->lang->translateString("update.check.failed"));
            return;
        }
        $filtered = filter_var_array($release, [
            "html_url" => [
                "filter" => FILTER_VALIDATE_URL,
            ],
            "tag_name" => [],
            "name" => [],
            "published_at" => [],
        ]);
        foreach ($filtered as $key => $value) {
            if ($value === null) {
                $logger->error($this->lang->translateString("update.check.failed.error", ["Response", "Required parameter " . $key . " is missing"]));
                $logger->error($this->lang->translateString("update.check.failed"));
                return;
            }
        }
        $compare = version_compare($filtered["tag_name"], $this->currentVersion);
        if ($compare === -1) {
            $logger->notice($this->lang->translateString("update.check.unknown", [$this->currentVersion, $filtered["tag_name"]]));
            return;
        } else if ($compare === 0) {
            $logger->info($this->lang->translateString("update.check.uptodate", [$this->currentVersion]));
            return;
        } else if ($compare === 1) {
            $logger->notice($this->lang->translateString("update.check.found", [$filtered["name"], $filtered["published_at"], $filtered["html_url"]]));
        }
    }
    
}