<?php
final class ResourceResolver
{
    public static function resolve(Share $share): ?Resource
    {
        $handler = ShareRegistry::getHandlerForShare($share);
        if (!$handler) {
            return null;
        }
        return $handler->resolveResource($share->resource_id, $share->resource_data, $share->owner_email);
    }

    public static function handler(Share $share): ?ResourceHandlerInterface
    {
        return ShareRegistry::getHandlerForShare($share);
    }
}
