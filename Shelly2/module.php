<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/ShellyHelper.php';

class Shelly2 extends IPSModule
{
    use Shelly;
    use
        ShellyRelayAction;
    use
        ShellyRollerAction;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyString('DeviceType', '');
        $this->RegisterPropertyString('Device', '');

        $this->RegisterProfileBooleanEx('Shelly.Reachable', 'Network', '', '', [
            [false, 'Offline',  '', 0xFF0000],
            [true, 'Online',  '', 0x00FF00]
        ]);

        $this->RegisterVariableBoolean('Shelly_Reachable', $this->Translate('Reachable'), 'Shelly.Reachable');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter für ReceiveData
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');

        switch ($this->ReadPropertyString('DeviceType')) {
            case 'relay':
                $this->SendDebug(__FUNCTION__ . ' Device Type: ', ' Relay', 0);
                $this->RegisterVariableBoolean('Shelly_State', $this->Translate('State'), '~Switch');
                $this->EnableAction('Shelly_State');
                $this->RegisterVariableBoolean('Shelly_State1', $this->Translate('State') . ' 2', '~Switch');
                $this->EnableAction('Shelly_State1');
                break;
            case 'roller':
                $this->SendDebug(__FUNCTION__ . ' Device Type: ', ' Roller', 0);
                $this->RegisterVariableInteger('Shelly_Roller', $this->Translate('Roller'), '~ShutterMoveStop');
                $this->EnableAction('Shelly_Roller');
                $this->RegisterVariableInteger('Shelly_RollerPosition', $this->Translate('Position'), '~Shutter');
                $this->EnableAction('Shelly_RollerPosition');
                break;
            default:
                $this->SendDebug(__FUNCTION__ . ' Device Type: ', 'No Device Type', 0);
        }

        switch ($this->ReadPropertyString('Device')) {
            case 'shelly2':
                $this->RegisterVariableFloat('Shelly_Power', $this->Translate('Power'), '~Watt.3680');
                $this->RegisterVariableFloat('Shelly_Energy', $this->Translate('Energy'), '~Electricity');
                break;
            case 'shelly2.5':
                $this->RegisterVariableFloat('Shelly_Power1', $this->Translate('Power 1'), '~Watt.3680');
                $this->RegisterVariableFloat('Shelly_Energy1', $this->Translate('Energy 1'), '~Electricity');
                $this->RegisterVariableFloat('Shelly_Power2', $this->Translate('Power 2'), '~Watt.3680');
                $this->RegisterVariableFloat('Shelly_Energy2', $this->Translate('Energy 2'), '~Electricity');
                $this->RegisterVariableFloat('Shelly_Temperature', $this->Translate('Device Temperature'), '~Temperature');
                $this->RegisterVariableBoolean('Shelly_Overtemperature', $this->Translate('Overtemperature'), '');
        }
        //Input
        $this->RegisterVariableBoolean('Shelly_Input', $this->Translate('Input 1'), '~Switch');
        $this->RegisterVariableBoolean('Shelly_Input1', $this->Translate('Input 2'), '~Switch');
        //Longpush
        $this->RegisterVariableBoolean('Shelly_Longpush', $this->Translate('Longpush Input 1'), '~Switch');
        $this->RegisterVariableBoolean('Shelly_Longpush1', $this->Translate('Longpush Input 2'), '~Switch');
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
                                    SetValue($this->GetIDForIdent('Shelly_Input'), 0);
                                    break;
                                case 1:
                                    SetValue($this->GetIDForIdent('Shelly_Input1'), 0);
                                    break;
                                default:
                                    break;
                            }
                            break;
                        case 1:
                            switch ($input) {
                                case 0:
                                    SetValue($this->GetIDForIdent('Shelly_Input'), 1);
                                    break;
                                case 1:
                                    SetValue($this->GetIDForIdent('Shelly_Input1'), 1);
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
                                    SetValue($this->GetIDForIdent('Shelly_Longpush'), 0);
                                    break;
                                case 1:
                                    SetValue($this->GetIDForIdent('Shelly_Longpush1'), 0);
                                    break;
                                default:
                                    break;
                            }
                            break;
                        case 1:
                            switch ($longpush) {
                                case 0:
                                    SetValue($this->GetIDForIdent('Shelly_Longpush'), 1);
                                    break;
                                case 1:
                                    SetValue($this->GetIDForIdent('Shelly_Longpush1'), 1);
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
                                    SetValue($this->GetIDForIdent('Shelly_State'), 0);
                                    break;
                                case 1:
                                    SetValue($this->GetIDForIdent('Shelly_State1'), 0);
                                    break;
                                default:
                                    break;
                            }
                            break;
                        case 'on':
                            switch ($relay) {
                                case 0:
                                    SetValue($this->GetIDForIdent('Shelly_State'), 1);
                                    break;
                                case 1:
                                    SetValue($this->GetIDForIdent('Shelly_State1'), 1);
                                    break;
                                default:
                                    break;
                            }
                            break;
                    }
                }
                if (fnmatch('*/roller/0*', $Buffer->Topic)) {
                    $this->SendDebug('Roller Payload', $Buffer->Payload, 0);
                    switch ($Buffer->Payload) {
                        case 'open':
                            SetValue($this->GetIDForIdent('Shelly_Roller'), 0);
                            break;
                        case 'stop':
                            SetValue($this->GetIDForIdent('Shelly_Roller'), 2);
                            break;
                        case 'close':
                            SetValue($this->GetIDForIdent('Shelly_Roller'), 4);
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
                    SetValue($this->GetIDForIdent('Shelly_RollerPosition'), intval($Buffer->Payload));
                }
                if (fnmatch('*/temperature', $Buffer->Topic)) {
                    $this->SendDebug('Temperature Payload', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Shelly_Temperature'), $Buffer->Payload);
                }
                if (fnmatch('*/overtemperature', $Buffer->Topic)) {
                    $this->SendDebug('Overtemperature Payload', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Shelly_Overtemperature'), boolval($Buffer->Payload));
                }
                if (fnmatch('*/online', $Buffer->Topic)) {
                    $this->SendDebug('Online Payload', $Buffer->Payload, 0);
                    switch ($Buffer->Payload) {
                        case 'true':
                            SetValue($this->GetIDForIdent('Shelly_Reachable'), true);
                            break;
                        case 'false':
                            SetValue($this->GetIDForIdent('Shelly_Reachable'), false);
                            break;
                    }
                }
                switch ($this->ReadPropertyString('Device')) {
                    case 'shelly2':
                        if (fnmatch('*/relay/power*', $Buffer->Topic)) {
                            $this->SendDebug('Power Payload', $Buffer->Payload, 0);
                            SetValue($this->GetIDForIdent('Shelly_Power'), $Buffer->Payload);
                        }
                        if (fnmatch('*/relay/energy*', $Buffer->Topic)) {
                            $this->SendDebug('Energy Payload', $Buffer->Payload, 0);
                            SetValue($this->GetIDForIdent('Shelly_Energy'), $Buffer->Payload / 60000);
                        }
                        break;
                    case 'shelly2.5':
                        if (fnmatch('*/0/power*', $Buffer->Topic)) {
                            $this->SendDebug('Power 0 Payload', $Buffer->Payload, 0);
                            SetValue($this->GetIDForIdent('Shelly_Power1'), $Buffer->Payload);
                        }
                        if (fnmatch('*/0/energy*', $Buffer->Topic)) {
                            $this->SendDebug('Energy 0 Payload', $Buffer->Payload, 0);
                            SetValue($this->GetIDForIdent('Shelly_Energy1'), $Buffer->Payload / 60000);
                        }
                        if (fnmatch('*/1/power*', $Buffer->Topic)) {
                            $this->SendDebug('Power 1 Payload', $Buffer->Payload, 0);
                            SetValue($this->GetIDForIdent('Shelly_Power2'), $Buffer->Payload);
                        }
                        if (fnmatch('*/1/energy*', $Buffer->Topic)) {
                            $this->SendDebug('Energy 1 Payload', $Buffer->Payload, 0);
                            SetValue($this->GetIDForIdent('Shelly_Energy2'), $Buffer->Payload / 60000);
                        }
                        break;
                }
            }
        }
    }

    private function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }
        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1) {
                throw new Exception($this->Translate('Variable profile type does not match for profile') . $Name, E_USER_NOTICE);
            }
        }
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }
}
