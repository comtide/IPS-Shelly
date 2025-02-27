<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ShellyModule.php';

class Shellyi3 extends ShellyModule
{
    public static $Variables = [
        ['Shelly_Input0', 'Input 1', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true],
        ['Shelly_Input1', 'Input 2', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true],
        ['Shelly_Input2', 'Input 3', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true],
        ['Shelly_InputEvent0', 'Input 1 Event', VARIABLETYPE_INTEGER, 'Shelly.i3Input', [], '', false, true],
        ['Shelly_InputEvent1', 'Input 2 Event', VARIABLETYPE_INTEGER, 'Shelly.i3Input', [], '', false, true],
        ['Shelly_InputEvent2', 'Input 3 Event', VARIABLETYPE_INTEGER, 'Shelly.i3Input', [], '', false, true],
        ['Shelly_Reachable', 'Reachable', VARIABLETYPE_BOOLEAN, 'Shelly.Reachable', [], '', false, true]
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProfileIntegerEx('Shelly.i3Input', 'ArrowRight', '', '', [
            [0, $this->Translate('shortpush'),  '', 0x08f26e],
            [1, $this->Translate('double shortpush'),  '', 0x07da63],
            [2, $this->Translate('triple shortpush'),  '', 0x06c258],
            [3, $this->Translate('longpush'),  '', 0x06a94d],
            [4, $this->Translate('shortpush + longpush'),  '', 0x59142],
            [5, $this->Translate('longpush + shortpush'),  '', 0x06600],
        ]);
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
                if (fnmatch('*/input/[0123]*', $Buffer->Topic)) {
                    $this->SendDebug('Input Payload', $Buffer->Payload, 0);
                    $ShellyTopic = explode('/', $Buffer->Topic);
                    $Key = count($ShellyTopic) - 1;
                    $index = $ShellyTopic[$Key];

                    switch ($Buffer->Payload) {
                        case 0:
                            $this->SetValue('Shelly_Input' . $index, false);
                            break;
                        case 1:
                            $this->SetValue('Shelly_Input' . $index, true);
                            break;
                    }
                }

                if (fnmatch('*/input_event/[0123]*', $Buffer->Topic)) {
                    $this->SendDebug('Input Payload', $Buffer->Payload, 0);
                    $ShellyTopic = explode('/', $Buffer->Topic);
                    $Key = count($ShellyTopic) - 1;
                    $index = $ShellyTopic[$Key];

                    $Payload = json_decode($Buffer->Payload);
                    $this->SendDebug('Input Payload', $Buffer->Payload, 0);
                    switch ($Payload->event) {
                        case 'S':
                            $this->SetValue('Shelly_InputEvent' . $index, 0);
                            break;
                        case 'SS':
                            $this->SetValue('Shelly_InputEvent' . $index, 1);
                            break;
                        case 'SSS':
                            $this->SetValue('Shelly_InputEvent' . $index, 2);
                            break;
                        case 'L':
                            $this->SetValue('Shelly_InputEvent' . $index, 3);
                            break;
                        case 'SL':
                            $this->SetValue('Shelly_InputEvent' . $index, 4);
                            break;
                        case 'LS':
                            $this->SetValue('Shelly_InputEvent' . $index, 5);
                            break;
                    }
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
            }
        }
    }
}