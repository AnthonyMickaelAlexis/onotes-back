<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use App\Http\Library\ApiHelpers;
use App\Models\Article;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    use ApiHelpers;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $articles = Article::with('user:id,pseudo,avatar')->with('tag')->get();

            if (!empty($articles)) {
                return $this->onSuccess($articles, 'All Articles');
            }
            return $this->onError(404, 'No Articles Found');

    }

    /**
     * Récupère les articles pour la homepage avec les utilisateurs qui les ont écrits.
     */
    public function homepage()
    {
        $articles = Article::with('user:id,pseudo,avatar')->with('tag')->orderBy("created_at", "desc")->take(10)->get();

        if (!empty($articles)) {
            return $this->onSuccess($articles, 'All Articles');
        }
        return $this->onError(404, 'No Articles Found');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if($this->IsAdmin($user) || $this->isUser($user)) {
            $validator = Validator::make($request->all(), $this->postValidationRules());

            if ($validator->passes()) {
                $article = Article::create([
                    'title' => $request->title,
                    'subtitle' => $request->subtitle,
                    'slug' => Str::slug($request->title),
                    'text_content' => $request->text_content,
                    'file_content' => $request->file_content,
                    'banner' => $request->banner,
                    'user_id' => $request->user()->id,
                    'subcategory_id' => $request->subcategory_id,
                ]);

                // Récupérer les tags sélectionnés par l'utilisateur
                $tags = $request->input('tags');

                // Synchroniser les tags avec l'article
                $article->tag()->sync($tags);

                // Vérifier si un nouveau tag a été créé
                $newTags = $request->input('newTags');
                if ($newTags) {
                    foreach ($newTags as $newTag) {
                            // Créer un nouveau tag
                            $tag = Tag::create([
                                'name' => $newTag['name'],
                                'slug' => Str::slug($newTag['name']),
                                'user_id' => $request->user()->id,
                                'logo' => $newTag['logo'],
                                'color' => $newTag['color'],
                                'bg_color' => $newTag['bg_color'],
                            ]);
                        // Synchroniser le nouveau tag avec l'article
                        $article->tag()->attach($tag->id);
                    }
                }

                return $this->onSuccess($article, 'Article Created');
            }
            return $this->onError(400, $validator->errors());
        }
        return $this->onError(401, 'Unauthorized');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $articles = Article::with('user:id,pseudo,avatar')->with('tag')->find($id);

        if (!empty($articles)) {
            return $this->onSuccess($articles, 'Article Found');
        }

        return $this->onError(404, 'Article Not Found');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $articleId)
    {
        $user = $request->user();

        // Vérifier si l'utilisateur est autorisé à éditer l'article
        if (!($this->IsAdmin($user) || $this->isUser($user))) {
            return $this->onError(401, 'Unauthorized');
        }

        // Récupérer l'article à éditer
        $article = Article::findOrFail($articleId);

        // Valider les données de la requête
        $validator = Validator::make($request->all(), $this->postValidationRules());

        if ($validator->passes()) {
            // Mettre à jour les données de l'article
            $article->update([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'slug' => Str::slug($request->title),
                'text_content' => $request->text_content,
                'file_content' => $request->file_content,
                'banner' => $request->banner,
                'subcategory_id' => $request->subcategory_id,
                'tags' => $request->tags,
            ]);

            // Récupérer les tags sélectionnés par l'utilisateur
            $tags = $request->input('tags');

            // Synchroniser les tags avec l'article
            $article->tag()->sync($tags);

            $newTags = $request->input('newTags');
            if ($newTags) {
                foreach ($newTags as $newTag) {
                    // Créer un nouveau tag
                    $tag = Tag::create([
                        'name' => $newTag['name'],
                        'slug' => Str::slug($newTag['name']),
                        'user_id' => $request->user()->id,
                        'logo' => $newTag['logo'],
                        'color' => $newTag['color'],
                        'bg_color' => $newTag['bg_color'],
                    ]);
                    // Synchroniser le nouveau tag avec l'article
                    $article->tag()->attach($tag->id);
                }
            }

            // Enregistrer les modifications
            $article->save();

            return $this->onSuccess([$article], 'Article Updated');
        }

        return $this->onError(400, $validator->errors());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();

        if($this->IsAdmin($user) || $this->isUser($user)) {
            $article = Article::find($id);
            if (!empty($article)) {
                $article->delete();
                return $this->onSuccess(null, 'Article Deleted');
            }
            return $this->onError(404, 'Article Not Found');
        }
        return $this->onError(401, 'Unauthorized');
    }
}
