<?php

namespace App;

enum MessageStatus: string
{
    case Delivered = 'delivered';
    case Read = 'read';
}
