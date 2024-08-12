<?php

namespace BajanVlogs\SuperCrates;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\utils\Config;

use pocketmine\utils\UUID;
use pocketmine\utils\TextFormat as TF;
use pocketmine\math\Vector3;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\nbt\tag\{
	CompoundTag, StringTag
};
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

use pocketmine\event\player\{
	PlayerInteractEvent, PlayerJoinEvent
};
use pocketmine\network\mcpe\protocol\{
	AddItemActorPacket, RemoveActorPacket
};
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;

use pocketmine\world\sound\PopSound;

class Main extends PluginBase implements Listener {
	public $Crate = [];
	public $locations = [
		"Voting"    => "-69:118:-54",
		"Elite"     => "-69:118:-44",
		"Legendary" => "-69:118:-49",
		"God" 	    => "-69:118:-39",
	];

	/** @var Config */
	public $items;

	public function onEnable(): void {
		$this->items = new Config($this->getDataFolder() . 'Items.yml', Config::YAML);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
                $this->saveResource("Items.yml");
	}

	public function onPlayerJoin(PlayerJoinEvent $event): void {
		$player = $event->getPlayer();
		foreach($this->locations as $crate => $xyz){
			$xyz = explode(":", $xyz);
			$pk = new AddPlayerPacket();
			$pk->uuid = UUID::fromRandom();
			$pk->username = "Chest";
			$pk->actorRuntimeId = Entity::nextRuntimeId();
			$pk->position = new Vector3((int)$xyz[0] + 0.5, (int)$xyz[1] + 1.5, (int)$xyz[2] + 0.5);
			$id = $pk->actorRuntimeId;
			$pk->item = Item::get(Item::AIR);
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
			$player->getNetworkSession()->sendDataPacket($pk);
			$this->Crate[$xyz[0] . $xyz[2] . "TEXT"] = $id;
		}
	}

	public function randomItem(string $key = null): Item {
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
			$item->addEnchantment(new EnchantmentInstance($enchantment, (int)$level)); 
		}
		if(isset($customname)) $item->setCustomName(TF::RESET . $customname);

		return $item;
	}

	public function getChestReady(Block $block): void {
		$cname = "";
		$pk = new AddPlayerPacket();
		$pk->uuid = UUID::fromRandom();
		$pk->username = "Chest";
		$pk->actorRuntimeId = Entity::nextRuntimeId();
		$pos = new Vector3($block->getPosition()->getX(), $block->getPosition()->getY(), $block->getPosition()->getZ());
		foreach($this->locations as $crate => $xyz){
			$xyz = explode(":", $xyz);
			$cpos = new Vector3((int)$xyz[0], (int)$xyz[1], (int)$xyz[2]);
			if($pos->equals($cpos)){
				$cname = $crate;
			}
		}
		$pk->position = new Vector3($block->getPosition()->getX() + .5, $block->getPosition()->getY() + 1.5, $block->getPosition()->getZ() + .5);
		$id = $pk->actorRuntimeId;
		$pk->item = Item::get(Item::AIR);
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
			$player->getNetworkSession()->sendDataPacket($pk);
		}
		unset($this->Crate[$block->getPosition()->getX() . $block->getPosition()->getZ() . "INUSE"]);
		$this->Crate[$block->getPosition()->getX() . $block->getPosition()->getZ() . "TEXT"] = $id;
	}

	public function closeChest(Block $chest): bool {
		unset($this->Crate[$chest->getPosition()->getX() . $chest->getPosition()->getZ() . "ITEM"]);
		unset($this->Crate[$chest->getPosition()->getX() . $chest->getPosition()->getZ() . "TEXT"]);

		return true;
	}

	public function despawnItem(Block $block): bool {
		if(!isset($this->Crate[$block->getPosition()->getX() . $block->getPosition()->getZ() . "ITEM"])) return false;
		$pk = new RemoveActorPacket();
		$pk->actorRuntimeId = $this->Crate[$block->getPosition()->getX() . $block->getPosition()->getZ() . "ITEM"];
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->getNetworkSession()->sendDataPacket($pk);
		}
		$pk = new RemoveActorPacket();
		$pk->actorRuntimeId = $this->Crate[$block->getPosition()->getX() . $block->getPosition()->getZ() . "TEXT"];
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->getNetworkSession()->sendDataPacket($pk);
		}

		return true;
	}

	public function spawnItem(Block $block, ?Item $items = null): bool {
		$block->getPosition()->getWorld()->addSound($block->getPosition(), new PopSound());
		$item = $items;
		if($items === null) $item = $this->randomItem();
		if($items === null) $item->setCustomName(TF::YELLOW . "? " . $item->getName() . TF::YELLOW . " ?");
		$pk = new AddItemActorPacket();
		$pk->actorRuntimeId = Entity::nextRuntimeId();
		$id = $pk->actorRuntimeId;
		$pk->position = new Vector3($block->getPosition()->getX() + .5, $block->getPosition()->getY() + 1, $block->getPosition()->getZ() + .5);
		$pk->item = $item;
		$pk->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, 1 << Entity::DATA_FLAG_IMMOBILE],
		];
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->getNetworkSession()->sendDataPacket($pk);
		}
		$this->Crate[$block->getPosition()->getX() . $block->getPosition()->getZ() . "ITEM"] = $id;

		return true;
	}

	public function onInteract(PlayerInteractEvent $event): void {
		$block = $event->getBlock();
		$player = $event->getPlayer();
		$item = $event->getItem();
		if(isset($this->Crate[$block->getPosition()->getX() . $block->getPosition()->getZ() . "INUSE"])) return;
		foreach($this->locations as $key => $loc){
			if($block->getPosition()->getX() . ":" . $block->getPosition()->getY() . ":" . $block->getPosition()->getZ() === $loc){
				if($item->getId() === Item::PAPER && $item->getName() === TF::RESET . TF::GOLD . $key . " Key"){
					$this->Crate[$block->getPosition()->getX() . $block->getPosition()->getZ() . "INUSE"] = true;
					$this->despawnItem($block);
					$player->getInventory()->removeItem(Item::get(Item::PAPER, 0, 1)->setCustomName(TF::RESET . TF::GOLD . $key . " Key"));
					$this->getScheduler()->scheduleRepeatingTask(new CrateAnimation($this, $block), 3);
				}
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
		if(strtolower($cmd->getName()) === "key"){
			if(!isset($args[0])) return false;
			if(!isset($args[1])){
				if(!($sender instanceof Player)) return false;
				$amount = 1;
				$player = $sender;
			}else{
				$amount = (int) $args[1];
				$player = $this->getServer()->getPlayerExact($args[1]);
				if($player === null){
					$sender->sendMessage(TF::RED . "That player cannot be found.");
					return true;
				}
			}
			$player->getInventory()->addItem(Item::get(Item::PAPER, 0, $amount)->setCustomName(TF::RESET . TF::GOLD . $args[0] . " Key"));
			return true;
		}

		return false;
	}
}
