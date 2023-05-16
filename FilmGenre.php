<?php

namespace common\modules\film\models;

use common\models\User;
use mohorev\file\UploadImageBehavior;
use odilov\multilingual\behaviors\MultilingualBehavior;
use soft\behaviors\CyrillicSlugBehavior;
use soft\behaviors\UploadBehavior;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "film_genre".
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $icon
 * @property string|null $image
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property bool $status [tinyint(1)]
 *
 * @property User $createdBy
 * @property-read string $imageUrl
 * @property-read string $svgUrl
 * @property-read FilmGenre[] $assigns
 * @property-read Film[] $films
 * @property-read int $filmsCount
 * @property-read int $assignsCount
 * @property User $updatedBy
 */
class FilmGenre extends \soft\db\ActiveRecord
{

    //<editor-fold desc="Parent" defaultstate="collapsed">

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'film_genre';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['slug', 'name'], 'string', 'max' => 255],
            [['image'], 'image', 'extensions' => 'jpg,jpeg,png', 'maxSize' => 1024 * 1024],
            [['icon'], 'file', 'extensions' => 'svg', 'maxSize' => 1024 * 1024],
            ['status', 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'yii\behaviors\TimestampBehavior',
            'yii\behaviors\BlameableBehavior',
            'slug' => [
                'class' => CyrillicSlugBehavior::class,
                'attribute' => 'name'
            ],
            'multilingual' => [
                'class' => MultilingualBehavior::class,
                'attributes' => ['name']
            ],
            'image' => [
                'class' => UploadImageBehavior::class,
                'attribute' => 'image',
                'deleteOriginalFile' => true,
                'scenarios' => ['default'],
                'path' => '@frontend/web/uploads/images/film_genre/{id}',
                'url' => '/uploads/images/film_genre/{id}',
                'thumbs' => [
                    'preview' => ['width' => 500],
                ],
            ],
            'svg' => [
                'class' => UploadBehavior::class,
                'attribute' => 'icon',
                'scenarios' => ['default'],
                'path' => '@frontend/web/uploads/images/film_genre/{id}',
                'url' => '/uploads/images/film_genre/{id}',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function labels()
    {
        return [
            'id' => 'ID',
            'name' => 'Janr nomi',
            'slug' => 'Slug',
            'icon' => 'Icon (SVG)',
            'image' => 'Rasm',
            'assignsCount' => "Bog'langan filmlar",
        ];
    }

    public static function find()
    {
        return parent::find()->multilingual();
    }

    /**
     * @return array
     */
    public static function map()
    {

        return ArrayHelper::map(self::find()->all(), 'id', 'name');
    }
    //</editor-fold>

    //<editor-fold desc="Relations" defaultstate="collapsed">

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'created_by']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUpdatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'updated_by']);
    }

    /**
     * @return \soft\db\ActiveQuery
     */
    public function getAssigns()
    {
        return $this->hasMany(FilmGenreAssign::className(), ['film_genre_id' => 'id']);
    }

    /**
     * @return \common\modules\film\models\query\FilmQuery
     */
    public function getFilms()
    {
        return $this->hasMany(Film::className(), ['id' => 'film_id'])->via('assigns')->nonPartial();
    }

    //</editor-fold>

    /**
     * @return int
     */
    public function getAssignsCount()
    {
        return (int)$this->getAssigns()->cache()->count();
    }

    /**
     * @return int
     */
    public function getFilmsCount()
    {
        return (int)$this->getFilms()->cache()->count();
    }

    //<editor-fold desc="Image and SVG" defaultstate="collapsed">

    /**
     * @return mixed
     */
    public function getSvgUrl()
    {
        return $this->getBehavior('svg')->getUploadUrl('icon');
    }

    /**
     * @return mixed
     */
    public function getImageUrl()
    {
        return $this->getBehavior('image')->getThumbUploadUrl('image', 'preview');
    }
    //</editor-fold>
}
