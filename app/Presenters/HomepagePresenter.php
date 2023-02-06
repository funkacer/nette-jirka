<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\PostFacade;
use Nette;
//use Nette\Application\UI\Form;

final class HomepagePresenter extends Nette\Application\UI\Presenter
{
    /* Původní
	private Nette\Database\Explorer $database;

	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}

	// ...
    public function renderDefault(): void
    {
        $this->template->posts = $this->database
            ->table('posts')
            ->order('created_at DESC')
            ->limit(5);
    }
    */

    private PostFacade $facade;

	public function __construct(PostFacade $facade)
	{
		$this->facade = $facade;
	}

	public function renderDefault(): void
	{
		$this->template->posts = $this->facade
			->getPublicArticles()
			->limit(5);
	}

}


