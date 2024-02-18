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
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\network\mcpe\protocol\AddItemEntityPacket;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\level\sound\PopSound;
use pocketmine\entity\Entity;
use pocketmine\level\particle\InkParticle;

class Main extends PluginBase implements Listener {

    public $Crate = [];
    public $locations = [
        "Voting"    => "-69:118:-54",
        "Elite"     => "-69:118:-44",
        "Legendary" => "-69:118:-49",
    ];

    /** @var Config */
    public $items;

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->saveResource("items.yml");
        $this->items = new Config($this->getDataFolder() . "items.yml", Config::YAML);
    }

    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        foreach ($this->locations as $crate => $xyz) {
            $xyz = explode(":", $xyz);
            $cpos = new Vector3($xyz[0], $xyz[1], $xyz[2]);
            $this->spawnCrate($crate, $cpos);
        }
    }

    public function randomItem(string $key = null) {
        $level = 0;
        if ($key === null) $key = $this->keys(rand(0, 2));
        $items = $this->items->getNested($key);
        $itemdetails = $items[array_rand($items)];
        $itemdetails = explode(":", $itemdetails);
        [$id, $damage, $count, $eid, $elvl, $customname] = $itemdetails;
        $item = Item::get((int)$id, (int)$damage, (int)$count);
        if (isset($eid)) {
            $enchantment = Enchantment::getEnchantment((int)$eid);
            $level = 1;
        }
        if (isset($elvl)) $level = $elvl;
        if (isset($enchantment)) {
            $item->addEnchantment($enchantment->setLevel((int)$level));
        }
        if (isset($customname)) $item->setCustomName(TF::RESET . $customname);

        return $item;
    }

    public function spawnCrate(string $crate, Vector3 $position) {
        $pk = new AddItemEntityPacket();
        $pk->entityRuntimeId = Entity::$entityCount++;
        $id = $pk->entityRuntimeId;
        $pk->type = Item::NETWORK_ID;
        $pk->position = new Vector3($position->x + 0.5, $position->y + 1.5, $position->z + 0.5);
        $pk->item = Item::get(0, 0, 0);
        $pk->motion = new Vector3(0, 0, 0);
        $flags = (
            (1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
            (1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
            (1 << Entity::DATA_FLAG_IMMOBILE)
        );
        $pk->metadata = [
            Entity::DATA_FLAGS   => [Entity::DATA_TYPE_LONG, $flags],
            Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, TF::AQUA . ucfirst($crate) . " " . TF::GOLD . "Crate"],
        ];
        $this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
        $this->Crate[$position->x . $position->z . "TEXT"] = $id;
    }

    public function despawnCrate(Vector3 $position) {
        if (!isset($this->Crate[$position->x . $position->z . "TEXT"])) return false;
        $pk = new RemoveEntityPacket();
        $pk->entityUniqueId = $this->Crate[$position->x . $position->z . "TEXT"];
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $player->dataPacket($pk);
        }
        unset($this->Crate[$position->x . $position->z . "TEXT"]);

        return true;
    }

    public function onPlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        foreach ($this->locations as $crate => $xyz) {
            $xyz = explode(":", $xyz);
            $cpos = new Vector3($xyz[0], $xyz[1], $xyz[2]);

            if ($block->equals($cpos->floor())) {
                $this->despawnCrate($cpos);
                $item = $this->randomItem($crate);
                $player->getInventory()->addItem($item);

                // Add particle effects
                $this->splashParticles($player);

                $player->getLevel()->addSound(new PopSound($player->asVector3()));
                $event->setCancelled();
            }
        }
    }

    private function splashParticles(Player $player) {
        $pk = new BlockEventPacket();
        $pk->x = (int)$player->x;
        $pk->y = (int)$player->y;
        $pk->z = (int)$player->z;
        $pk->case1 = 2000; // Bonemeal particles
        $pk->case2 = 0;
        $player->sendDataPacket($pk);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "givekey") {
            if (count($args) !== 3) {
                $sender->sendMessage(TF::RED . "Usage: /givekey {player} {key} {amount}");
                return false;
            }
            
            $player = $this->getServer()->getPlayer($args[0]);
            if (!$player instanceof Player) {
                $sender->sendMessage(TF::RED . "Player not found");
                return false;
            }
            
            $key = (int) $args[1];
            $amount = (int) $args[2];
            $keyItem = Item::get($key, 0, $amount);
            $player->getInventory()->addItem($keyItem);
            $sender->sendMessage(TF::GREEN . "Given $amount of key $key to " . $player->getName());
            return true;
        }
        return false;
    }
}
