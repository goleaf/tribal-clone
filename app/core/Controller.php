<?php
declare(strict_types=1);

abstract class Controller
{
    protected $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $pageTitle = $data['title'] ?? 'Tribal Wars';

        require __DIR__ . '/../../header.php';
        require __DIR__ . '/../views/' . $view . '.php';
        require __DIR__ . '/../../footer.php';
    }
}
