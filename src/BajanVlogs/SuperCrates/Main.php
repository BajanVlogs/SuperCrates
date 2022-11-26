<?php

namespace BajanVlogs\SuperCrates;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\utils\Config;

use pocketmine\utils\UUID;
use pocketmine\utils\TextFormat as TF;
use pocketmine\math\Vector3;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\block\Block;
use pocketmine\item\Item;
//use pocketmine\entity\Item as ItemEntity;
use pocketmine\nbt\tag\NamedTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

use pocketmine\event\player\{
	PlayerInteractEvent, PlayerJoinEvent
};
use pocketmine\network\mcpe\protocol\{
	AddItemEntityPacket, BlockEventPacket, RemoveEntityPacket
};
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\handler\PacketHandler;

use pocketmine\level\sound\PopSound;

class Main extends PluginBase implements Listener {
	public $Crate = [];
	public $locations = [
		"Voting"    => "-69:118:-54",
		"Elite"     => "-69:118:-44",
		"Legendary" => "-69:118:-49",
	];

	/** @var Config */
	public $items;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->saveResource("items.yml");
		$this->items = new Config($this->getDataFolder() . "items.yml", Config::YAML);
	}

	public function PlayerJoinEvent(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		foreach($this->locations as $crate => $xyz){
			$xyz = explode(":", $xyz);
			$cpos = new Vector3($xyz[0], $xyz[1], $xyz[2]);
			$pk = new AddPlayerPacket();
			$pk->uuid = UUID::fromRandom();
			$pk->username = "Chest";
			$pk->entityRuntimeId = Entity::$entityCount++;
			$pk->position = new Vector3((int)$xyz[0] + 0.5, (int)$xyz[1] + 1.5, (int)$xyz[2] + 0.5);
			$id = $pk->entityRuntimeId;
			$pk->item = Item::get(0, 0, 0);
			$flags = (
				(1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
				(1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
				(1 << Entity::DATA_FLAG_IMMOBILE)
			);
			$pk->metadata = [
				Entity::DATA_FLAGS   => [Entity::DATA_TYPE_LONG, $flags],
				Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, TF::AQUA . ucfirst($crate) . " " . TF::GOLD . "Crate"],
				Entity::DATA_SCALE   => [Entity::DATA_TYPE_FLOAT, 0],
			];
			$player->dataPacket($pk);
			$this->Crate[$xyz[0] . $xyz[2] . "TEXT"] = $id;
		}
	}

	public function randomItem(string $key = null){
		$level = 0;
		if($key == null) $key = $this->keys(rand(0, 2));
		$items = $this->items->getNested($key);
		$itemdetails = $items[array_rand($items)];
		$itemdetails = explode(":", $itemdetails);
		$id = $itemdetails[0];
		$damage = $itemdetails[1];
		$count = $itemdetails[2];
		if(isset($itemdetails[5])) $customname = $itemdetails[5];
		if(isset($itemdetails[3])) $eid = $itemdetails[3];
		if(isset($itemdetails[4])) $elvl = $itemdetails[4];
		$item = Item::get((int)$id, (int)$damage, (int)$count);
		if(isset($eid)){
			$enchantment = Enchantment::getEnchantment((int)$eid);
			$level = 1;
		}
		if(isset($elvl)) $level = $elvl;
		if(isset($enchantment)){
			$item->addEnchantment($enchantment->setLevel((int)$level)); 
		}
		if(isset($customname)) $item->setCustomName(TF::RESET . $customname);

		return $item;
	}

	public function getChestReady(Block $block){
		$cname = "";
		$pk = new AddPlayerPacket();
		$pk->uuid = UUID::fromRandom();
		$pk->username = "Chest";
		$pk->entityRuntimeId = Entity::$entityCount++;
		$pos = new Vector3($block->x, $block->y, $block->z);
		foreach($this->locations as $crate => $xyz){
			$xyz = explode(":", $xyz);
			$cpos = new Vector3((int)$xyz[0], (int)$xyz[1], (int)$xyz[2]);
			if($pos->equals($cpos)){
				$cname = $crate;
			}
		}
		$pk->position = new Vector3($block->x + .5, $block->y + 1.5, $block->z + .5);
		$id = $pk->entityRuntimeId;
		$pk->item = Item::get(0, 0, 0);
		$flags = (
			(1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
			(1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
			(1 << Entity::DATA_FLAG_IMMOBILE)
		);
		$pk->metadata = [
			Entity::DATA_FLAGS   => [Entity::DATA_TYPE_LONG, $flags],
			Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, TF::AQUA . ucfirst($cname) . " " . TF::GOLD . "Crate"],
			Entity::DATA_SCALE   => [Entity::DATA_TYPE_FLOAT, 0],
		];
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->dataPacket($pk);
		}
		unset($this->Crate[$block->x . $block->z . "INUSE"]);
		$this->Crate[$block->x . $block->z . "TEXT"] = $id;
	}

	public function closeChest(Block $chest){
		$pk = new BlockEventPacket();
		$pk->x = $chest->getX();
		$pk->y = $chest->getY();
		$pk->z = $chest->getZ();
		$pk->case1 = 1;
		$pk->case2 = 0;
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->dataPacket($pk);
		}
		unset($this->Crate[$chest->x . $chest->z . "ITEM"]);
		unset($this->Crate[$chest->x . $chest->z . "TEXT"]);

		return true;
	}

	public function despawnItem($block){
		if(!isset($this->Crate[$block->x . $block->z . "ITEM"])) return false;
		$pk = new RemoveEntityPacket();
		$pk->entityUniqueId = $this->Crate[$block->x . $block->z . "ITEM"];
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->dataPacket($pk);
		}
		$pk = new RemoveEntityPacket();
		$pk->entityUniqueId = $this->Crate[$block->x . $block->z . "TEXT"];
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->dataPacket($pk);
		}

		return true;
	}

	public function spawnItem(Block $block, $items = null){
		$block->getLevel()->addSound(new PopSound($block));
		$item = $items;
		$pk = new BlockEventPacket();
		$pk->x = $block->getX();
		$pk->y = $block->getY();
		$pk->z = $block->getZ();
		$pk->case1 = 1;
		$pk->case2 = 2;
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->dataPacket($pk);
		}
		if($items == null) $item = $this->randomItem();
		if($items == null) $item->setCustomName(TF::YELLOW . "? " . $item->getName() . TF::YELLOW . " ?");
		$pk = new AddItemEntityPacket();
		$pk->entityRuntimeId = Entity::$entityCount++;
		$id = $pk->entityRuntimeId;
		$pk->type = ItemEntity::NETWORK_ID;
		$pk->position = new Vector3($block->x + .5, $block->y + 1, $block->z + .5);
		$pk->item = $item;
		$pk->motion = new Vector3(0,0,0);
		$flags = 0;
		$flags |= 1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG;
		$flags |= 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG;
		$flags |= 1 << Entity::DATA_FLAG_IMMOBILE;
		$pk->metadata = [
			Entity::DATA_FLAGS   => [Entity::DATA_TYPE_LONG, $flags],
			Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $item->getName()],
		];
		$this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
		$pk = new AddPlayerPacket();
		$pk->uuid = UUID::fromRandom();
		$pk->username = "CHEST_1";
		$pk->entityRuntimeId = Entity::$entityCount++;
		$pk->position = new Vector3($block->x + .5, $block->y + 1.5, $block->z + .5);
		$id1 = $pk->entityRuntimeId;
		$pk->item = Item::get(0, 0, 0);
		$flags = (
			(1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
			(1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
			(1 << Entity::DATA_FLAG_IMMOBILE)
		);
		$pk->metadata = [
			Entity::DATA_FLAGS   => [Entity::DATA_TYPE_LONG, $flags],
			Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $item->getName()],
			Entity::DATA_SCALE   => [Entity::DATA_TYPE_FLOAT, 0],
		];
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->dataPacket($pk);
		}
		$this->Crate[$block->x . $block->z . "INUSE"] = $id;
		$this->Crate[$block->x . $block->z . "ITEM"] = $id;
		$this->Crate[$block->x . $block->z . "TEXT"] = $id1;

		return true;
	}

	public function PlayerInteractEvent(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$x = $block->getX();
		$y = $block->getY();
		$z = $block->getZ();
		$pos = new Vector3($x, $y, $z);
		$item = $event->getItem();
		foreach($this->locations as $crate => $xyz){
			$xyz = explode(":", $xyz);
			$cpos = new Vector3($xyz[0], $xyz[1], $xyz[2]);
			if($pos->equals($cpos)){
				if(isset($item->getNamedTag()->key)){
					$tag = $item->getNamedTag()->key->getValue();
					if($tag == $crate){
						if(isset($this->Crate[$block->x . $block->z . "INUSE"])){
							$event->setCancelled();
							$player->sendMessage(TF::RED . "This crate is in use, please wait...");

							return false;
						}else{
							$player->getInventory()->removeItem(Item::get($item->getId(), 0, 1));
							if(isset($this->Crate[$block->x . $block->z . "TEXT"])){
								$pk = new RemoveEntityPacket();
								$pk->entityUniqueId = $this->Crate[$block->x . $block->z . "TEXT"];
								foreach($this->getServer()->getOnlinePlayers() as $playero){
									$playero->dataPacket($pk);
								}
							}
							$player->sendMessage(TF::GREEN . "(!) Opening $crate crate!");
							$event->setCancelled();
							$prize = $this->randomItem($tag);
							$task = new Task($this, $block, $prize, $player);
							$this->getServer()->getScheduler()->scheduleRepeatingTask($task, 1);

							return true;
						}
					}
				}
			}
		}
		return true;
	}

	public function keys(int $key) : string {
		switch($key){
			case 0:
				return "Voting";
			case 1:
				return "Elite";
			case 2:
				return "Legendary";
		}
		return "null";
	}

	public function keysToInt(string $key):string{
		switch($key){
			case "Voting":
				return 0;
			case "Elite":
				return 1;
			case "Legendary":
				return 2;
		}
		return "null";
	}

	public function giveKey(Player $player, int $key, int $amount = 1){
		$item = Item::get(340, 0, $amount);
		$enchant = Enchantment::getEnchantment(-1);
		$item->addEnchantment($enchant);
		$keyname = $this->keys($key);
		$keyn = TF::WHITE . TF::OBFUSCATED . "||||" . TF::RESET . TF::GREEN . TF::BOLD . "$keyname Charm" . TF::RESET . TF::WHITE . TF::OBFUSCATED . "||||" . TF::RESET;
		$item->setCustomName(TF::RESET . $keyn);
		$nbt = $item->getNamedTag() ?? new CompoundTag("", []);
		$nbt->key = new StringTag("key", $this->keys($key));
		$item->setNamedTag($nbt);
		$player->sendMessage(TF::GREEN . "You have been rewarded " . $this->keys($key));

		return $player->getInventory()->addItem($item);
	}

	public function onCommand(CommandSender $player, Command $command, string $label, array $args): bool{
		switch($command->getName()){
			case "givekey":
				if(!$player->hasPermission("key.give")){
					$player->sendMessage(TF::RED . "No perms");

					return false;
				}
				if(!isset($args[1])){
					$player->sendMessage(TF::RED . "/givekey <player> <key id> <amount>");

					return false;
				}
				$amount = 1;
				if(isset($args[2])) $amount = $args[2];
				$giveplayer = $this->getServer()->getPlayer($args[0]);
				if($giveplayer == null){
					$player->sendMessage(TF::RED . "Player not online");

					return false;
				}
				if($this->keys($args[1]) == null){
					$player->sendMessage(TF::RED . "Key not found");

					return false;
				}
				$this->giveKey($giveplayer, $args[1], $amount);

				return true;
		}

		return true;
	}

}
