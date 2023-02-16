<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\PostFacade;
use Nette;
//use Nette\Application\UI\Form;

class Thumbnail {

	protected $id;
	protected $menu;
	protected $color;
	protected $picture;
	protected $reference;
	protected $order;

	public function __construct ($argId, $argMenu, $argColor, $argPicture, $argReference, $argOrder) {
		$this->id = $argId;
		$this->menu = $argMenu;
		$this->color = $argColor;
		$this->picture = $argPicture;
		$this->reference = $argReference;
		$this->order = $argOrder;
	}

	public function getId () {
		return $this->id;
	}

	public function getMenu () {
		return $this->menu;
	}

	public function getColor () {
		return $this->color;
	}

	public function getPicture () {
		return $this->picture;
	}

	public function getReference () {
		return $this->reference;
	}

	public function getOrder () {
		return $this->order;
	}
}

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
		$poleObrazku = scandir("./img/");
		$this->template->poleObrazku = $poleObrazku;

		//https://play.google.com/store/apps/details?id=funkacer.ceskesvatkykalendar
		$poleThumbnails = [
			'weather' => new Thumbnail ("weather", "Předpověď počasí", "black", "weather_picture.png", "https://funkacer.cz/weather-app/", 1),
			'kalendar' => new Thumbnail ("kalendar", "České svátky kalendář", "black", "app_picture.png", "https://play.google.com/store/apps/details?id=funkacer.ceskesvatkykalendar", 2),
			'penzion' => new Thumbnail ("penzion", "Prima-penzion", "black", "primapenzion-main.jpg", "https://funkacer.cz/prima-penzion/", 3),
			'prevodnik' => new Thumbnail ("prevodnik", "Převodník teplot", "black", "temp_picture.png", "https://funkacer.cz/prevodnik-teplot/", 4),
			'nasobilka' => new Thumbnail ("nasobilka", "Malá násobilka", "black", "nasobilka_picture.png", "MalaNasobilka:default", 5),
			'strom' => new Thumbnail ("strom", "Strom produktů", "black", "strom_picture.png", "Strom:default", 6)
		];
		$this->template->poleThumbnails = $poleThumbnails;

		$this->template->posts = $this->facade
			->getPublicArticles()
			->limit(5);
	}

}


