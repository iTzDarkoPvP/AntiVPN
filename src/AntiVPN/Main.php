<?php

namespace AntiVPN;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class Main extends PluginBase implements Listener {

    private $whitelist;
    private $messages;

    public function onEnable(){
        $this->getLogger()->info(TextFormat::GREEN . "§l§fAnti§6VPN §l§8| §r§fPlugin activado correctamente en su V2");

        $this->saveDefaultConfig();
        $this->reloadConfig();

        $this->messages = [
            "kick-message" => $this->getConfig()->get("kick-message", "Has sido expulsado por usar una VPN de pais {pais}."),
            "global-broadcast-message" => $this->getConfig()->get("global-broadcast-message", "El jugador {player} intento entrar con vpn de {pais} al servidor y ha sido expulsado."),
            "command-no-permission" => $this->getConfig()->get("command-no-permission", "§cNo tienes permiso para usar este comando."),
            "command-usage" => $this->getConfig()->get("command-usage", "§eUso: /vpn add <jugador> o /vpn del <jugador>"),
            "command-player-added" => $this->getConfig()->get("command-player-added", "§aEl jugador '{player}' ha sido agregado a la whitelist de VPN."),
            "command-player-already-whitelisted" => $this->getConfig()->get("command-player-already-whitelisted", "§eEl jugador '{player}' ya esta en la whitelist de VPN."),
            "command-player-removed" => $this->getConfig()->get("command-player-removed", "§aEl jugador '{player}' ha sido eliminado de la whitelist de VPN."),
            "command-player-not-whitelisted" => $this->getConfig()->get("command-player-not-whitelisted", "§eEl jugador '{player}' no esta en la whitelist de VPN.")
        ];

        $this->whitelist = $this->getConfig()->get("vpn-whitelist", []);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(){
        $this->getLogger()->info(TextFormat::RED . "§l§fAnti§6VPN §l§8| §r§fPlugin desactivado");
        $this->getConfig()->set("vpn-whitelist", $this->whitelist);
        $this->saveConfig();
    }

    public function onPlayerPreLogin(PlayerPreLoginEvent $event){
        $playerName = strtolower($event->getPlayer()->getName());
        $ip = $event->getPlayer()->getAddress();

        if (in_array($playerName, $this->whitelist)){
            $this->getLogger()->info("El jugador§c " . $playerName . " §festá en la whitelist de VPN. Permitido.");
            return;
        }

        $vpnCountryName = $this->checkVPN($ip);

        if ($vpnCountryName !== false){
            $kickMessage = str_replace("{pais}", $vpnCountryName, $this->messages["kick-message"]);
            $globalBroadcastMessage = str_replace(["{player}", "{pais}"], [$event->getPlayer()->getName(), $vpnCountryName], $this->messages["global-broadcast-message"]);

            $event->setCancelled(true);
            $event->setKickMessage(TextFormat::RED . $kickMessage);

            $this->getServer()->broadcastMessage(TextFormat::YELLOW . $globalBroadcastMessage);
            $this->getLogger()->info("VPN detectada para el jugador§c " . $event->getPlayer()->getName() . " §fdesde §c" . $vpnCountryName . ".");
        }
    }

    private function checkVPN($ip){
        $url = "http://ip-api.com/json/" . $ip . "?fields=country,hosting";
        $data = @file_get_contents($url);

        if ($data === false){
            $this->getLogger()->error("Error al conectar con IP-API.com para la IP: " . $ip);
            return false;
        }

        $json = json_decode($data, true);

        if ($json === null || !isset($json["country"]) || !isset($json["hosting"])){
            $this->getLogger()->error("Respuesta invalida de IP-API.com para la IP: " . $ip . " - " . $data);
            return false;
        }

        if ($json["hosting"] === true){
            return $json["country"];
        }

        return false;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        if (strtolower($command->getName()) === "vpn"){
            if (!$sender->hasPermission("antivpn.admin")){
                $sender->sendMessage($this->messages["command-no-permission"]);
                return true;
            }

            if (count($args) < 2){
                $sender->sendMessage($this->messages["command-usage"]);
                return true;
            }

            $action = strtolower($args[0]);
            $playerName = strtolower($args[1]);

            switch ($action){
                case "add":
                    if (!in_array($playerName, $this->whitelist)){
                        $this->whitelist[] = $playerName;
                        $this->getConfig()->set("vpn-whitelist", $this->whitelist);
                        $this->saveConfig();
                        $sender->sendMessage(str_replace("{player}", $playerName, $this->messages["command-player-added"]));
                    } else {
                        $sender->sendMessage(str_replace("{player}", $playerName, $this->messages["command-player-already-whitelisted"]));
                    }
                    break;
                case "del":
                    $key = array_search($playerName, $this->whitelist);
                    if ($key !== false){
                        unset($this->whitelist[$key]);
                        $this->whitelist = array_values($this->whitelist);
                        $this->getConfig()->set("vpn-whitelist", $this->whitelist);
                        $this->saveConfig();
                        $sender->sendMessage(str_replace("{player}", $playerName, $this->messages["command-player-removed"]));
                    } else {
                        $sender->sendMessage(str_replace("{player}", $playerName, $this->messages["command-player-not-whitelisted"]));
                    }
                    break;
                default:
                    $sender->sendMessage($this->messages["command-usage"]);
                    break;
            }
            return true;
        }
        return false;
    }
}
