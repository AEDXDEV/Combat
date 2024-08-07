<?php

namespace AEDXDEV\Combat;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\entity\projectile\Projectile;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;

class Main extends PluginBase implements Listener {
  
  use SingletonTrait;
  
  // [Player1:Player2 => Time]
  private array $combat = [];
  
  public Config $config;
  
  private bool $isPluginEnabled = true;
  private bool $sameCombat = false;
  private bool $sendMessages = true;
  private array $messages = [];
  private int $combatTime = 10;
  
	public function onEnable(): void{
	  self::setInstance($this);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$config = new Config($this->getDataFolder() . "config.yml", Config::YAML, [
		  "Enable" => true,
		  "CancelIfNotInSameCombat" => false,
		  "sendMessages" => true,
		  "Messages" => [
		    "Start" => "§eYou are now in combat with {PLAYER}",
		    "InSameCombat" => "§cYou cannot proceed because you are not fighting the same opponent. Please focus on your current combat!",
		    "End" => "§eYou are no longer in combat."
		  ],
		  "Time" => 10,
		]);
		$this->config = $config;
	  $this->isPluginEnabled = $config->get("Enable", false);
	  $this->sameCombat = $config->get("CancelIfNotInSameCombat", false);
	  $this->sendMessages = $config->get("sendMessages", false);
	  $this->messages = $config->get("Messages", [
	    "Start" => "§eYou are now in combat with {PLAYER}",
	    "InSameCombat" => "§cYou cannot proceed because you are not fighting the same opponent. Please focus on your current combat!",
	    "End" => "§eYou are no longer in combat."
	  ]);
	  $this->combatTime = $config->get("Time", 10);
	  $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void{
	    $this->handleCombatTask();
		}), 20);
	}
	
	public function onDamage(EntityDamageEvent $event): void{
	  if ($event instanceof EntityDamageByEntityEvent) {
	    $damager = $event->getDamager();
	    $entity = $event->getEntity();
	    if (!$this->isPluginEnabled || $event->isCancelled())return;
	    if ($event instanceof EntityDamageByChildEntityEvent) {
	      $projectile = $event->getChild();
	      if ($projectile !== null && !$projectile instanceof Projectile)return;
	    }
	    if ($damager instanceof Player && $entity instanceof Player) {
	      if ($entity->isCreative() || $damager->isCreative())return;
	      if ($this->isInSameCombat($damager, $entity) && $this->sameCombat) {
	        if ($this->sendMessages) {
	          $damager->sendMessage($this->messages["InSameCombat"]);
	        }
	        $event->cancel();
	        return;
	      }
	      if($entity->getHealth() <= $event->getFinalDamage()){
	        $this->removeCombat($entity);
	        return;
	      }
	      $this->addCombat($damager, $entity);
	    }
	  }
	}
  
  public function onQuit(PlayerQuitEvent $event){
    $player = $event->getPlayer();
    if ($this->isInCombat($player)) {
      $this->removeCombat($player);
    }
  }
  
  private function isInCombat(Player $player): bool{
	  $name = $player->getName();
	  foreach ($this->combat as $key => $time) {
	    if (strpos($key, $name) !== false) {
	      return true;
	    }
	  }
    return false;
  }
	
	public function addCombat(Player $damager, Player $target): void{
	  $key = $damager->getName() . ":" . $target->getName();
	  if ($this->isInCombat($damager) || $this->isInCombat($target)) {
	    return;
	  }
	  if ($target->getCurrentWindow() !== null){
	    $target->removeCurrentWindow();
	  }
	  $this->combat[$key] = $this->combatTime;
	  if ($this->sendMessages) {
	    $msg = str_replace("{PLAYER}", "", $this->messages["Start"]);
	    $damager->sendMessage($msg . $target->getName());
	    $target->sendMessage($msg . $damager->getName());
	  }
  }
  
  public function getPlayerCombat(Player $player): ?Player{
    $playerName = $player->getName();
    $target = null;
    foreach ($this->combat as $key => $time) {
      [$name1, $name2] = explode(":", $key);
      if ($playerName === $name1) {
        $target = $name2;
      }
      if ($playerName === $name2) {
        $target = $name1;
      }
    }
    return $target !== null ? $this->getServer()->getPlayerExact($target) : null;
  }
  
  public function isInSameCombat(Player $player1, Player $player2): bool{
    $key1 = $player1->getName() . ":" . $player2->getName();
    $key2 = $player2->getName() . ":" . $player1->getName();
    return isset($this->combat[$key1]) || isset($this->combat[$key2]);
  }
  
  public function removeCombat(Player $player): void{
    $name = $player->getName();
    foreach ($this->combat as $key => $time) {
      if (strpos($key, $name) !== false) {
        unset($this->combat[$key]);
        if ($this->sendMessages) {
    	    $player->sendMessage($this->messages["End"]);
    	    $p = $this->getServer()->getPlayerExact(str_replace([$name, ":"], "", $key));
    	    $p->sendMessage($this->messages["End"]);
    	  }
      }
    }
  }
	
	private function handleCombatTask(): void{
	  foreach ($this->combat as $key => $time) {
	    if ($time <= 0) {
	      unset($this->combat[$key]);
	    } else {
	      $this->combat[$key]--;
	    }
	  }
	}
}
