<?php
/*
 *  @author Shukurullo Odilov <shukurullo0321@gmail.com>
 *  @link telegram: https://t.me/yii2_dasturchi
 *  @date 13.05.2022, 16:26
 */

namespace common\modules\film\controllers;

use common\modules\film\actions\FilmDeleteVideoAction;
use common\modules\film\actions\FilmUploadVideoAction;
use common\modules\film\models\FilmActor;
use common\modules\film\models\FilmComment;
use common\modules\film\models\LikeDislike;
use common\modules\film\models\search\FilmCommentSearch;
use common\modules\film\models\search\FilmSeasonSearch;
use common\modules\user\models\LastSeenFilm;
use Throwable;
use Yii;
use common\modules\film\models\Film;
use common\modules\film\models\search\FilmSearch;
use soft\web\SoftController;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;
use zxbodya\yii2\galleryManager\GalleryManagerAction;

class FilmController extends SoftController
{

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'delete-video' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['admin', 'system-user', 'call-center'],
                    ],
                ],
            ]
        ];
    }


    public function actions()
    {
        return [
            'upload-video' => [
                'class' => FilmUploadVideoAction::class
            ],
            'delete-video' => [
                'class' => FilmDeleteVideoAction::class,
            ],
            'galleryApi' => [
                'class' => GalleryManagerAction::class,
                // mappings between type names and model classes (should be the same as in behaviour)
                'types' => [
                    'film' => Film::class
                ]
            ],
        ];
    }

    //<editor-fold desc="CRUD" defaultstate="collapsed">

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new FilmSearch();
        $query = Film::find()->nonPartial()->with(['category', 'type', 'genres'])
            ->withLikesCount()
            ->withDislikesCount()
            ->withCommentsCount()
            ->withLastSeensCount()
//            ->withPartsCount()
            ->localized();
        $dataProvider = $searchModel->search($query);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @param integer $id
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = Film::findModel($id);
        return $this->render('view', ['model' => $model]);
    }

    /**
     * @return string
     */
    public function actionCreate()
    {
        $model = new Film([
            'stream_status_id' => Film::NO_STREAM,
        ]);

        if ($model->load(Yii::$app->request->post())) {

            if ($model->save()) {
                $model->createGenreAssigns();
                $model->createActorAssigns();
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }
        return $this->render('create', ['model' => $model]);
    }

    /**
     * @param integer $id
     * @return string
     * @throws \Yii\web\NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionUpdate($id)
    {
        $model = Film::findModel($id);

        if ($model->getIsPartial()) {
            return $this->redirect(['update-part', 'id' => $model->id]);
        }

        $model->film_genres = ArrayHelper::getColumn($model->genres, 'id');
        $model->film_actors = ArrayHelper::getColumn($model->actors, 'id');

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {

            $transaction = Yii::$app->db->beginTransaction();

            if ($model->save(false) && $model->updateGenreAssigns() && $model->updateActorAssigns()) {
                $transaction->commit();
                return $this->redirect(['view', 'id' => $model->id]);
            }

            $transaction->rollBack();
        }

        return $this->render('update', ['model' => $model]);
    }

    /**
     * @param integer $id
     * @return mixed
     * @throws Throwable
     * @throws \Yii\web\NotFoundHttpException
     */
    public function actionDelete($id)
    {
        $model = Film::findModel($id);

        if ($model->getIsSerial() && $model->partsCount > 0) {
            forbidden("Diqqat!. Ushbu serial ichida qismlar borligi uchun o'chirishga ruxsat berilmaydi!. 
            Serialni o'chriish uchun avval ichidagi qismlarni o'chriib chiqing");
        }

        if ($model->isStreaming()) {

            forbidden("Diqqat!. Hozirda ushbu video qayta ishlanmoqda. Shu sababli filmni o'chirishga ruxsat berilmaydi!
            <br>Birozdan so'ng qayta urinib ko'ring! ");
        }


        return $this->ajaxCrud($model)->deleteAction();
    }

    public function actionEditImages($id)
    {
        $product = Film::findModel($id);
        return $this->render('editImages', [
            'model' => $product
        ]);
    }
    //</editor-fold>

    //<editor-fold desc="Queue" defaultstate="collapsed">
    public function actionQueue()
    {
        $searchModel = new FilmSearch();
        $query = Film::find()
            ->andWhere(['stream_status_id' => [Film::IN_QUEUE, Film::IS_STREAMING]])
            ->orderBy(['stream_status_id' => SORT_DESC, 'id' => SORT_ASC]);

        $dataProvider = $searchModel->search($query);

        return $this->render('queue', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }


    //</editor-fold>

    //<editor-fold desc="Fasllar" defaultstate="collapsed">
    /**
     * @param $id int
     * @return string
     * @throws \Yii\web\NotFoundHttpException
     */
    public function actionSeasons($id)
    {
        $model = Film::findModel($id);
        $searchModel = new FilmSeasonSearch();
        $query = $model->getSeasons()->withPartsCount()->withActivePartsCount();
        $dataProvider = $searchModel->search($query);
        return $this->render('seasons', [
            'model' => $model,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    //</editor-fold>

    //<editor-fold desc="Parts - Serial Qismlari" defaultstate="collapsed">


    /**
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionParts($id)
    {
        $model = Film::findModel($id);
        $query = Film::find()
            ->andWhere(['parent_id' => $id])
            ->with(['season'])
            ->withLikesCount()
            ->withDislikesCount()
            ->withCommentsCount()
            ->withLastSeensCount()
            ->orderBy(['sort_order' => SORT_DESC]);

        $searchModel = new FilmSearch();

        $dataProvider = $searchModel->search($query);

        return $this->render('parts', [
            'model' => $model,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider
        ]);
    }

    /**
     * @return string
     * @throws NotFoundHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionCreatePart($id)
    {
        $parentModel = Film::findModel($id);

        if (!$parentModel->getIsSerial()) {
            forbidden();
        }

        $model = new Film([
            'parent_id' => $id,
            'serial_type_id' => Film::SERIAL_TYPE_PART,
//            'scenario' => Film::SCENARIO_PART
        ]);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create-part', ['model' => $model, 'parentModel' => $parentModel]);
    }

    /**
     * @param $id
     * @return string|\yii\web\Response
     * @throws \Yii\web\NotFoundHttpException
     */
    public function actionUpdatePart($id)
    {
        $model = Film::findModel($id);

        if (!$model->getIsPartial()) {
            return $this->redirect(['update', 'id' => $model->id]);
        }

        if ($model->loadSave()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update-part', ['model' => $model]);
    }

    //</editor-fold>

    //<editor-fold desc="Actors">

    /**
     * @param $id int Film ID
     * @return string
     * @throws \Yii\web\NotFoundHttpException
     */
    public function actionActors($id)
    {

        $model = Film::findModel($id);

        $query = FilmActor::find()
            ->andWhere(['film_id' => $id]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        return $this->render('actors', ['dataProvider' => $dataProvider, 'model' => $model]);
    }

    //</editor-fold>

    //<editor-fold desc="Comments">

    /**
     * @param $id int Film ID
     * @return string
     * @throws \Yii\web\NotFoundHttpException
     */
    public function actionComments($id)
    {

        $model = Film::findModel($id);

        $searchModel = new FilmCommentSearch();
        $dataProvider = $searchModel->search($model->getComments());

        return $this->render('comments', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'model' => $model
        ]);
    }

    /**
     * @param $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionLikes($id)
    {
        $model = Film::findModel($id);

        $query = LikeDislike::find()
            ->andWhere(['film_id' => $id])
            ->andWhere(['type_id' => LikeDislike::TYPE_LIKE]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC
                ]
            ]
        ]);

        return $this->render('likes', [
            'dataProvider' => $dataProvider,
            'model' => $model
        ]);
    }

    /**
     * @param $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionDislikes($id)
    {
        $model = Film::findModel($id);

        $query = LikeDislike::find()
            ->andWhere(['film_id' => $id])
            ->andWhere(['type_id' => LikeDislike::TYPE_DISLIKE]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC
                ]
            ]
        ]);

        return $this->render('dislikes', [
            'dataProvider' => $dataProvider,
            'model' => $model
        ]);
    }

    /**
     * @param $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionViews($id)
    {
        $model = Film::findModel($id);

        $query = LastSeenFilm::find()
            ->andWhere(['film_id' => $id]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'defaultPageSize' => 50,
            ],
        ]);

        return $this->render('views', [
            'model' => $model,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @param $id
     * @return void
     * @throws \Yii\web\NotFoundHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionAddToQueue($id)
    {
        $model = Film::findModel($id);


        if (!$model->issetOrgVideo()) {
            forbidden("UShbu filmda original mavjud emas!");
        }

        try {
            $model->deleteQueue();
            $model->pushToQueue();
        } catch (\Exception $e) {
            return not_found($e->getMessage());
        }


        if ($this->isAjax) {
            $this->formatJson();
            $ajaxCrud = $this->ajaxCrud;
//            $ajaxCrud->forceReload = false;
            return $ajaxCrud->closeModal();
        }

        return $this->back();

    }

    //</editor-fold>
}
