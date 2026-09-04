<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Enums;

/**
 * Generic classification for an `OrderItem` line. `PRODUCT` is a soft
 * reference to a catalog product (no FK); `SERVICE`/`TEXT` are catalog-free
 * lines (installation fees, packaging, ...); `CUSTOM` is a host-defined
 * polymorphic line via `itemable_type`/`itemable_id`.
 */
enum LineItemTypeEnum: string
{
    case PRODUCT = 'product';
    case SERVICE = 'service';
    case TEXT = 'text';
    case CUSTOM = 'custom';
}
