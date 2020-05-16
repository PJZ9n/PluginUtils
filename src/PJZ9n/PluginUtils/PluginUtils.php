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
use pocketmine\plugin\Plugin;

class PluginUtils
{
    
    /** @var Plugin */
    private $plugin;
    
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }
    
    public function initConfig(): void
    {
        if ($this->plugin->saveDefaultConfig()) {
            //First save
            $this->replaceConfigLanguage();
        }
    }
    
    /**
     * @internal normally
     */
    public function replaceConfigLanguage(): void
    {
        $lang = $this->plugin->getServer()->getLanguage()->getLang();
        $this->plugin->getLogger()->debug("Replace language to " . $lang);
        $this->plugin->getConfig()->set("language", $lang);//Replace language
        $this->plugin->saveConfig();
    }
    
    public function updateConfig(): void
    {
        $this->plugin->getLogger()->debug("Update config.yml");
        $fp = $this->plugin->getResource("config.yml");
        $configYaml = "";
        while (!feof($fp)) {
            $configYaml .= fgets($fp);
        }
        fclose($fp);
        $oldCount = count($this->plugin->getConfig()->getAll(), COUNT_RECURSIVE);
        $this->plugin->getConfig()->setDefaults(yaml_parse($configYaml));//replace
        $newCount = count($this->plugin->getConfig()->getAll(), COUNT_RECURSIVE);
        $this->plugin->getLogger()->debug("Added " . ($newCount - $oldCount) . " variables");
        $this->plugin->saveConfig();
    }
    
    public function initLanguage(string $fallbackLang): BaseLang
    {
        $this->plugin->getLogger()->debug("Init language");
        foreach ($this->plugin->getResources() as $path => $resource) {
            if (strpos($path, "locale/") === 0 && $resource->getExtension() === "ini") {
                $this->plugin->getLogger()->debug("Save language file: " . $path);
                $this->plugin->saveResource($path, true);
            }
        }
        $config = $this->plugin->getConfig();
        $lang = new BaseLang((string)$config->get("language", $fallbackLang), $this->plugin->getDataFolder() . "locale/", $fallbackLang);
        $this->plugin->getLogger()->info($lang->translateString("language.selected", [$lang->getName()]));
        return $lang;
    }
    
    public function checkUpdate(string $updateHost, BaseLang $baseLang): void
    {
        $currentVersion = $this->plugin->getDescription()->getVersion();
        $logger = $this->plugin->getLogger();
        $this->plugin->getServer()->getAsyncPool()->submitTask(new CheckUpdateTask(
            $updateHost,
            $baseLang,
            $currentVersion,
            $logger
        ));
    }
    
}