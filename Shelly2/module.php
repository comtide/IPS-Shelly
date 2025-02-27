<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/ShellyModule.php';

class Shelly2 extends ShellyModule
{
    public static $Variables = [
        ['Shelly_State', 'State', VARIABLETYPE_BOOLEAN, '~Switch', ['shelly2', 'shelly2.5'], 'relay', true, true],
        ['Shelly_State1', 'State 2', VARIABLETYPE_BOOLEAN, '~Switch', ['shelly2', 'shelly2.5'], 'relay', true, true],

        ['Shelly_Roller', 'Roller', VARIABLETYPE_INTEGER, '~ShutterMoveStop', ['shelly2', 'shelly2.5'], 'roller', true, true],
        ['Shelly_RollerPosition', 'Position', VARIABLETYPE_INTEGER, '~Shutter', ['shelly2', 'shelly2.5'], 'roller', true, true],
        ['Shelly_RollerStopReason', 'Stop Reason', VARIABLETYPE_STRING, '', ['shelly2', 'shelly2.5'], 'roller', false, true],

        ['Shelly_Power', 'Power', VARIABLETYPE_FLOAT, '~Watt.3680', ['shelly2'], '', false, true],
        ['Shelly_Energy', 'Energy', VARIABLETYPE_FLOAT, '~Electricity', ['shelly2'], '', false, true],

        ['Shelly_Power1', 'Power 1', VARIABLETYPE_FLOAT, '~Watt.3680', ['shelly2.5'], '', false, true],
        ['Shelly_Energy1', 'Energy 1', VARIABLETYPE_FLOAT, '~Electricity', ['shelly2.5'], '', false, true],
        ['Shelly_Power2', 'Power 2', VARIABLETYPE_FLOAT, '~Watt.3680', ['shelly2.5'], '', false, true],
        ['Shelly_Energy2', 'Energy 2', VARIABLETYPE_FLOAT, '~Electricity', ['shelly2.5'], '', false, true],
        ['Shelly_Temperature', 'Device Temperature', VARIABLETYPE_FLOAT, '~Temperature', ['shelly2.5'], '', false, true],
        ['Shelly_Overtemperature', 'Overtemperature', VARIABLETYPE_BOOLEAN, '', ['shelly2.5'], '', false, true],

        ['Shelly_Input', 'Input 1', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true],
        ['Shelly_Input1', 'Input 2', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true],
        ['Shelly_Longpush', 'Longpush 1', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true],
        ['Shelly_Longpush1', 'Longpush 2', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true],

        ['Shelly_Reachable', 'Reachable', VARIABLETYPE_BOOLEAN, 'Shelly.Reachable', '', '', false, true]
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('DeviceType', '');
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Shelly_State':
                $this->SwitchMode(0, $Value);
                break;
            case 'Shelly_State1':
                $this->SwitchMode(1, $Value);
                break;
            case 'Shelly_Roller':
                switch ($Value) {
                    case 0:
                        $this->MoveUp();
                        break;
                    case 2:
                        $this->Stop();
                        break;
                    case 4:
                        $this->MoveDown();
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__ . 'Ident: Shelly_Roller', 'Invalid Value:' . $Value, 0);
                }
            break;
            case 'Shelly_RollerPosition':
                $this->SendDebug(__FUNCTION__ . ' Value Shelly_RollerPosition', $Value, 0);
                $this->Move($Value);
                break;
            }
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('JSON', $JSONString, 0);
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $data = json_decode($JSONString);

            switch ($data->DataID) {
                case '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}': // MQTT Server
                    $Buffer = $data;
                    break;
                case '{DBDA9DF7-5D04-F49D-370A-2B9153D00D9B}': //MQTT Client
                    $Buffer = json_decode($data->Buffer);
                    break;
                default:
                    $this->LogMessage('Invalid Parent', KL_ERROR);
                    return;
            }

            $this->SendDebug('MQTT Topic', $Buffer->Topic, 0);

            if (property_exists($Buffer, 'Topic')) {
                if (fnmatch('*/input/[01]', $Buffer->Topic)) {
                    $this->SendDebug('Input Payload', $Buffer->Payload, 0);
                    $input = $this->getChannelRelay($Buffer->Topic);
                    switch ($Buffer->Payload) {
                        case 0:
                            switch ($input) {
                                case 0:
                                    $this->SetValue('Shelly_Input', 0);
                                    break;
                                case 1:
                                    $this->SetValue('Shelly_Input1', 0);
                                    break;
                                default:
                                    break;
                            }
                            break;
                        case 1:
                            switch ($input) {
                                case 0:
                                    $this->SetValue('Shelly_Input', 1);
                                    break;
                                case 1:
                                    $this->SetValue('Shelly_Input1', 1);
                                    break;
                                default:
                                    break;
                            }
                            break;
                    }
                }

                if (fnmatch('*/longpush/[01]', $Buffer->Topic)) {
                    $this->SendDebug('Longpush Payload', $Buffer->Payload, 0);
                    $longpush = $this->getChannelRelay($Buffer->Topic);
                    switch ($Buffer->Payload) {
                        case 0:
                            switch ($longpush) {
                                case 0:
                                    $this->SetValue('Shelly_Longpush', 0);
                                    break;
                                case 1:
                                    $this->SetValue('Shelly_Longpush1', 0);
                                    break;
                                default:
                                    break;
                            }
                            break;
                        case 1:
                            switch ($longpush) {
                                case 0:
                                    $this->SetValue('Shelly_Longpush', 1);
                                    break;
                                case 1:
                                    $this->SetValue('Shelly_Longpush1', 1);
                                    break;
                                default:
                                    break;
                            }
                            break;
                    }
                }

                if (fnmatch('*/relay/[01]', $Buffer->Topic)) {
                    $this->SendDebug('State Payload', $Buffer->Payload, 0);
                    $relay = $this->getChannelRelay($Buffer->Topic);
                    $this->SendDebug(__FUNCTION__ . ' Relay', $relay, 0);

                    //Power prüfen und in IPS setzen
                    switch ($Buffer->Payload) {
                        case 'off':
                            switch ($relay) {
                                case 0:
                                    $this->SetValue('Shelly_State', 0);
                                    break;
                                case 1:
                                    $this->SetValue('Shelly_State1', 0);
                                    break;
                                default:
                                    break;
                            }
                            break;
                        case 'on':
                            switch ($relay) {
                                case 0:
                                    $this->SetValue('Shelly_State', 1);
                                    break;
                                case 1:
                                    $this->SetValue('Shelly_State1', 1);
                                    break;
                                default:
                                    break;
                            }
                            break;
                    }
                }
                if (fnmatch('*/roller/stop_reason', $Buffer->Topic)) {
                    $this->SendDebug('Roller Payload', $Buffer->Payload, 0);
                    $this->SetValue('Shelly_RollerStopReason', $Buffer->Payload);
                }
                if (fnmatch('*/roller/0', $Buffer->Topic)) {
                    $this->SendDebug('Roller Payload', $Buffer->Payload, 0);
                    switch ($Buffer->Payload) {
                        case 'open':
                            $this->SetValue('Shelly_Roller', 0);
                            break;
                        case 'stop':
                            $this->SetValue('Shelly_Roller', 2);
                            break;
                        case 'close':
                            $this->SetValue('Shelly_Roller', 4);
                            break;
                        default:
                            if (!fnmatch('*/roller/0/pos*', $Buffer->Topic)) {
                                $this->SendDebug(__FUNCTION__ . ' Roller', 'Invalid Value: ' . $Buffer->Payload, 0);
                            }
                            break;
                    }
                }
                if (fnmatch('*/roller/0/pos*', $Buffer->Topic)) {
                    $this->SendDebug('Roller Payload', $Buffer->Payload, 0);
                    $this->SetValue('Shelly_RollerPosition', intval($Buffer->Payload));
                }
                if (fnmatch('*/temperature', $Buffer->Topic)) {
                    $this->SendDebug('Temperature Payload', $Buffer->Payload, 0);
                    $this->SetValue('Shelly_Temperature', $Buffer->Payload);
                }
                if (fnmatch('*/overtemperature', $Buffer->Topic)) {
                    $this->SendDebug('Overtemperature Payload', $Buffer->Payload, 0);
                    $this->SetValue('Shelly_Overtemperature', boolval($Buffer->Payload));
                }
                if (fnmatch('*/online', $Buffer->Topic)) {
                    $this->SendDebug('Online Payload', $Buffer->Payload, 0);
                    switch ($Buffer->Payload) {
                        case 'true':
                            $this->SetValue('Shelly_Reachable', true);
                            break;
                        case 'false':
                            $this->SetValue('Shelly_Reachable', false);
                            break;
                    }
                }
                switch ($this->ReadPropertyString('Device')) {
                    case 'shelly2':
                        if (fnmatch('*/relay/power*', $Buffer->Topic)) {
                            $this->SendDebug('Power Payload', $Buffer->Payload, 0);
                            $this->SetValue('Shelly_Power', $Buffer->Payload);
                        }
                        if (fnmatch('*/relay/energy*', $Buffer->Topic)) {
                            $this->SendDebug('Energy Payload', $Buffer->Payload, 0);
                            $this->SetValue('Shelly_Energy', $Buffer->Payload / 60000);
                        }
                        break;
                    case 'shelly2.5':
                        if (fnmatch('*/0/power*', $Buffer->Topic)) {
                            $this->SendDebug('Power 0 Payload', $Buffer->Payload, 0);
                            $this->SetValue('Shelly_Power1', $Buffer->Payload);
                        }
                        if (fnmatch('*/0/energy*', $Buffer->Topic)) {
                            $this->SendDebug('Energy 0 Payload', $Buffer->Payload, 0);
                            $this->SetValue('Shelly_Energy1', $Buffer->Payload / 60000);
                        }
                        if (fnmatch('*/1/power*', $Buffer->Topic)) {
                            $this->SendDebug('Power 1 Payload', $Buffer->Payload, 0);
                            $this->SetValue('Shelly_Power2', $Buffer->Payload);
                        }
                        if (fnmatch('*/1/energy*', $Buffer->Topic)) {
                            $this->SendDebug('Energy 1 Payload', $Buffer->Payload, 0);
                            $this->SetValue('Shelly_Energy2', $Buffer->Payload / 60000);
                        }
                        break;
                }
            }
        }
    }

    private function MoveDown()
    {
        $Topic = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/roller/0/command';
        $Payload = 'close';
        $this->sendMQTT($Topic, $Payload);
    }

    private function MoveUp()
    {
        $Topic = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/roller/0/command';
        $Payload = 'open';
        $this->sendMQTT($Topic, $Payload);
    }

    private function Move($position)
    {
        $Topic = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/roller/0/command/pos';
        $Payload = strval($position);
        $this->sendMQTT($Topic, $Payload);
    }

    private function Stop()
    {
        $Topic = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/roller/0/command';
        $Payload = 'stop';
        $this->sendMQTT($Topic, $Payload);
    }

    private function SwitchMode(int $relay, bool $Value)
    {
        $Topic = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/relay/' . $relay . '/command';
        if ($Value) {
            $Payload = 'on';
        } else {
            $Payload = 'off';
        }
        $this->sendMQTT($Topic, $Payload);
    }
}
