<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use BackedEnum;

/**
 * Code of a HierarchyMessage. Each operation has its own codes (from the WSDL):
 * HierarchyLoadMessage (Load, Hide, Delete), HierarchySaveMessage, HierarchyNodeMessage and
 * HierarchyAccessMessage (Save), HierarchyBindMessage and HierarchyUnbindMessage.
 *
 *   if ($message->code === HierarchySaveMessage::Touched) { … }
 */
interface HierarchyMessageCode extends BackedEnum
{
}
