<?php
final class Resource
{
    public string $type;
    public string $id;
    public string $app;
    public string $title;
    public array $data;

    public function __construct(string $type, string $id, string $app, string $title, array $data = [])
    {
        $this->type = $type;
        $this->id = $id;
        $this->app = $app;
        $this->title = $title;
        $this->data = $data;
    }

    public function getTitle(): string
    {
        return $this->title !== '' ? $this->title : $this->id;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
}
