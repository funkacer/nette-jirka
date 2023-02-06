<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

final class PostPresenter extends Nette\Application\UI\Presenter
{
	private Nette\Database\Explorer $database;

	public function __construct(Nette\Database\Explorer $database)
	{
		$this->database = $database;
	}

	public function renderShow(int $postId): void
    {
        $countPosts = count($this->database->table('posts'));

        /*
        if ($postId == 0) {
            $postId = $countPosts;
        }
        if ($postId > $countPosts) {
            $postId = 1;
        }
        */

        $post = $this->database
            ->table('posts')
            ->get($postId);
        if (!$post) {
            $this->error('Stránka nebyla nalezena');
        }

        //logika pro další a předchozí
        if ($postId == $countPosts) {
            $postIdNext = 1;
        } else {
            $postIdNext = $postId + 1;
        }
        if ($postId == 1) {
            $postIdPrev = $countPosts;
        } else {
            $postIdPrev = $postId - 1;
        }



        $this->template->post = $post;
        $this->template->comments = $post->related('comments')->order('created_at');

        //$this->template->ahoj = "Počet záznamů celkem: ".$countPosts;
        $this->template->ahoj = $countPosts;
        
        $this->template->nextid = $postIdNext;
        $this->template->previd = $postIdPrev;

        if (!isset($this->template->important)) {
            $this->template->important = 1;
        }
        

        //$this->flashMessage('Položka nebyla smazána.');
    }

    protected function createComponentCommentForm(): Form
    {
            $form = new Form; // means Nette\Application\UI\Form

            $form->addText('name', 'Jméno:')
                ->setRequired();

            $form->addEmail('email', 'E-mail:');

            $form->addTextArea('content', 'Komentář:')
                ->setRequired();

            $form->addSubmit('send', 'Publikovat komentář');

            $form->onSuccess[] = [$this, 'commentFormSucceeded'];

            return $form;
    }

    public function commentFormSucceeded(\stdClass $data): void
    {
        $postId = $this->getParameter('postId');

        $this->database->table('comments')->insert([
            'post_id' => $postId,
            'name' => $data->name,
            'email' => $data->email,
            'content' => $data->content,
        ]);

        $this->flashMessage('Děkuji za komentář', 'success');
        $this->redirect('this');
        //$this->template->important = 0; //už nic neudělá
    }



}
?>