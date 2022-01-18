<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2019 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\DataProvider\Filters;

use Illuminatech\DataProvider\Exceptions\InvalidQueryException;

class FilterExact extends FilterRelatedRecursive
{
    /**
     * {@inheritdoc}
     */
    protected function applyInternal(object $source, string $target, string $name, $value): object
    {
        if (!is_scalar($value)) {
            throw new InvalidQueryException('Filter "' . $name . '" requires scalar value.');
        }

        return $source->where($target, '=', $value);
    }
}
