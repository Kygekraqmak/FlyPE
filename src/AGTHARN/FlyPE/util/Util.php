<?php

/* 
 *  ______ _  __     _______  ______ 
 * |  ____| | \ \   / /  __ \|  ____|
 * | |__  | |  \ \_/ /| |__) | |__   
 * |  __| | |   \   / |  ___/|  __|  
 * | |    | |____| |  | |    | |____ 
 * |_|    |______|_|  |_|    |______|
 *
 * FlyPE, is an advanced fly plugin for PMMP.
 * Copyright (C) 2020 AGTHARN
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace AGTHARN\FlyPE\util;

use pocketmine\utils\TextFormat as C;
use pocketmine\math\Vector3;
use pocketmine\Player;

use AGTHARN\FlyPE\tasks\ParticleTask;
use AGTHARN\FlyPE\tasks\FlightSpeedTask;
use AGTHARN\FlyPE\tasks\EffectTask;
use AGTHARN\FlyPE\lists\ParticleList;
use AGTHARN\FlyPE\lists\SoundList;
use AGTHARN\FlyPE\Main;

use jojoe77777\FormAPI\SimpleForm;
use JackMD\UpdateNotifier\UpdateNotifier;
use onebone\economyapi\EconomyAPI;

class Util {
	
	/**
     * plugin
     * 
     * @var Main
     */
    private $plugin;
	
	/**
	 * __construct
	 *
	 * @param  Main $plugin
	 * @return void
	 */
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
	/**
	 * openFlyUI
	 *
	 * @param  Player $player
	 * @return object
	 */
	public function openFlyUI(Player $player) {
		$form = new SimpleForm(function (Player $player, $data) {
            
        if ($data === null) return;
			
		switch ($data) {
			case 0:
				$cost = $this->plugin->getConfig()->get("buy-fly-cost");
				$name = $player->getName();
				
				if ($this->plugin->getConfig()->get("pay-for-fly") === true) {
					if (EconomyAPI::getInstance()->myMoney($player) < $cost) {
						$player->sendMessage(C::RED . str_replace("{cost}", $cost, str_replace("{name}", $name, $this->plugin->getConfig()->get("not-enough-money"))));
						return;
					}
					if ($player->getAllowFlight() === false) {
						$player->sendMessage(C::GREEN . str_replace("{cost}", $cost, str_replace("{name}", $name, $this->plugin->getConfig()->get("buy-fly-successful"))));
						if ($this->doLevelChecks($player) === true) {
							$this->toggleFlight($player);
							EconomyAPI::getInstance()->reduceMoney($player, $cost);
						}
						return;
					} elseif ($this->doLevelChecks($player) === false && $player->getAllowFlight() === true) {
						$this->toggleFlight($player);
						return;
					}
				} else {
					$this->toggleFlight($player);
				}
			break;
			case 1:
				// exit button
			break;
		}
		});
		
		/** @phpstan-ignore-next-line */
		if ($this->plugin->getConfig()->get("enable-fly-ui") === true && $this->plugin->getConfig()->get("pay-for-fly") === true && $this->plugin->getConfig()->get("custom-ui-texts") === false) {
			$cost = $this->plugin->getConfig()->get("buy-fly-cost");
					
			$form->setTitle("§l§7< §2FlyUI §7>");
			$form->addButton("§aToggle Fly §e(Costs $ {$cost})");
			$form->addButton("§cExit");
			$form->sendToPlayer($player);
			return $form;
		} elseif ($this->plugin->getConfig()->get("enable-fly-ui") === true && $this->plugin->getConfig()->get("pay-for-fly") === false && $this->plugin->getConfig()->get("custom-ui-texts") === false) {
			$form->setTitle("§l§7< §6FlyUI §7>");
			$form->addButton("§aToggle Fly");
			$form->addButton("§cExit");
			$form->sendToPlayer($player);
			return $form;
		} elseif ($this->plugin->getConfig()->get("custom-ui-texts") === true) {
			$cost = $this->plugin->getConfig()->get("buy-fly-cost");

			$form->setTitle($this->plugin->getConfig()->get("fly-ui-title"));
			$form->addButton(str_replace("{cost}", $cost, $this->plugin->getConfig()->get("fly-ui-toggle")));
			$form->addButton($this->plugin->getConfig()->get("fly-ui-exit"));
			$form->sendToPlayer($player);
			return $form;
		}
	}
	
	/**
	 * doLevelChecks
	 *
	 * @param  Player $player
	 * @return bool
	 */
	public function doLevelChecks(Player $player): bool {
		$levelName = $player->getLevel()->getName();
		$name = $player->getName();

		if ($player->getGamemode() === Player::CREATIVE && $player->getAllowFlight() === true) {
			$player->sendMessage(C::RED . str_replace("{name}", $name, $this->plugin->getConfig()->get("disable-fly-creative")));
			return false;
		}

		if ($this->plugin->getConfig()->get("mode") === "blacklist" && !in_array($player->getLevel()->getName(), $this->plugin->getConfig()->get("blacklisted-worlds"))) {
			return true;
		}
		if ($this->plugin->getConfig()->get("mode") === "whitelist" && in_array($player->getLevel()->getName(), $this->plugin->getConfig()->get("whitelisted-worlds"))) {
			return true;
		}
		$player->sendMessage(C::RED . str_replace("{world}", $levelName, $this->plugin->getConfig()->get("flight-not-allowed")));
		return false;
	}
	
	/**
	 * toggleFlight
	 *
	 * @param  Player $player
	 * @return void
	 */
	public function toggleFlight(Player $player): void {
		$name = $player->getName();

		if ($player->getAllowFlight() === true) {
			$player->setAllowFlight(false);
			$player->setFlying(false);
			$player->sendMessage(C::RED . str_replace("{name}", $name, $this->plugin->getConfig()->get("toggled-flight-off")));
			if ($this->plugin->getConfig()->get("enable-fly-sound") === true) {
				$player->getLevel()->addSound($this->getSoundList()->getSound($this->plugin->getConfig()->get("fly-disabled-sound"), new Vector3($player->x, $player->y, $player->z)));
			}
		} else {
			$player->setAllowFlight(true);
			$player->setFlying(true);
			$player->sendMessage(C::GREEN . str_replace("{name}", $name, $this->plugin->getConfig()->get("toggled-flight-on")));
			if ($this->plugin->getConfig()->get("enable-fly-sound") === true) {
				$player->getLevel()->addSound($this->getSoundList()->getSound($this->plugin->getConfig()->get("fly-enabled-sound"), new Vector3($player->x, $player->y, $player->z)));
			}
		}
	}
	    
    /**
     * getParticles
     *
     * @return mixed|object|resource
     */
    public function getParticleList() {
        return new ParticleList();
	}
		
	/**
	 * getSoundList
	 *
	 * @return mixed|object|resource
	 */
	public function getSoundList() {
        return new SoundList();
	}
		
	/**
	 * checkConfiguration
	 *
	 * @return void
	 */
	public function checkConfiguration(): void {
		if ($this->plugin->getConfig()->get("enable-fly-particles") === true) {
			$this->plugin->getScheduler()->scheduleRepeatingTask(new ParticleTask($this->plugin, $this), $this->plugin->getConfig()->get("fly-particle-rate"));
		}

		if ($this->plugin->getConfig()->get("enable-fly-effects") === true) {
			$this->plugin->getScheduler()->scheduleRepeatingTask(new EffectTask($this->plugin), $this->plugin->getConfig()->get("fly-effect-check-rate"));
		}
		
		if ($this->plugin->getConfig()->get("fly-speed-mod") === true) {
			if ($this->plugin->getConfig()->get("fly-speed") > 3) {
				$this->plugin->getLogger()->warning("The fly speed limit is 3! The fly speed modification will be turned off.");
				return;
			}
			$this->plugin->getScheduler()->scheduleRepeatingTask(new FlightSpeedTask($this->plugin), $this->plugin->getConfig()->get("fly-speed-check-rate"));
		}
	}
	
	/**
	 * checkDepend
	 *
	 * @return bool
	 */
	public function checkDepend(): bool {
		if ($this->plugin->getServer()->getPluginManager()->getPlugin("EconomyAPI") === null && $this->plugin->getConfig()->get("pay-for-fly") === true && $this->plugin->getConfig()->get("enable-fly-ui") === true) {
			$this->plugin->getLogger()->warning("EconomyAPI not found while pay-for-fly is turned on!");
			$this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
			return false;
		}
		return true;
	}
}
