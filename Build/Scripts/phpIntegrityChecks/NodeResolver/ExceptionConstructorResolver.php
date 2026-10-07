<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "be_groups".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

// Copied from the TYPO3 Core 14.3 (Build/Scripts/phpIntegrityChecks), namespace adapted.

namespace Cretection\BeGroups\Build\PhpIntegrityChecks\NodeResolver;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class ExceptionConstructorResolver extends NodeVisitorAbstract
{
    public function enterNode(Node $node): void
    {
        if (!$node instanceof Node\Expr\New_) {
            return;
        }

        if (!$node->class instanceof Node\Name\FullyQualified) {
            return;
        }
        try {
            $name = $node->class->name;
            $reflectionClass = new \ReflectionClass($name);
        } catch (\ReflectionException) {
            return;
        }
        if (!$reflectionClass->isSubclassOf(\Exception::class)) {
            return;
        }
        $constructorParameters = $reflectionClass->getConstructor()->getParameters();
        $node->class->setAttribute('constructor', $constructorParameters);
    }

}
