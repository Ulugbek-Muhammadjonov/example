<?php

namespace common\modules\film\models;

use common\models\query\UserQuery;
use common\models\User;
use common\modules\country\models\Country;
use common\modules\film\behaviors\FilmBehavior;
use common\modules\film\behaviors\FilmSluggableBehavior;
use common\modules\film\models\query\FilmQuery;
use common\modules\film\traits\FavoriteFilmTrait;
use common\modules\film\traits\FilmAccessTrait;
use common\modules\film\traits\FilmActorTrait;
use common\modules\film\traits\FilmCommentTrait;
use common\modules\film\traits\FilmGalleryTrait;
use common\modules\film\traits\FilmGenreTrait;
use common\modules\film\traits\FilmImageTrait;
use common\modules\film\traits\FilmLastSeenTrait;
use common\modules\film\traits\FilmLikeDislikeTrait;
use common\modules\film\traits\FilmMediaSourceTrait;
use common\modules\film\traits\FilmOriginalVideoTrait;
use common\modules\film\traits\FilmPriceTypeTrait;
use common\modules\film\traits\FilmSerialPartsTrait;
use common\modules\film\traits\FilmSerialTypeTrait;
use common\modules\film\traits\FilmStreamTrait;
use common\modules\film\traits\FilmTestStatus;
use common\modules\film\traits\FilmTestStatusTrait;
use common\modules\film\traits\FilmViewsTrait;
use common\modules\user\models\FavoriteFilm;
use Imagine\Image\Box;
use mohorev\file\UploadImageBehavior;
use odilov\multilingual\behaviors\MultilingualBehavior;
use soft\behaviors\CyrillicSlugBehavior;
use soft\behaviors\TimestampConvertorBehavior;
use soft\db\ActiveQuery;
use soft\db\ActiveRecord;
use soft\helpers\Html;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use zxbodya\yii2\galleryManager\GalleryBehavior;

/**
 * This is the model class for table "film".
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string|null $slug
 * @property int|null $year Yili
 * @property string|null $image Rasm
 * @property int|null $sort_order
 * @property int|null $category_id Film kategoriyasi
 * @property int|null $type_id Film turi
 * @property int|null $country_id
 * @property int|null $serial_type_id
 * @property int|null $parent_id
 * @property int|null $season_id Fasl
 * @property int|null $price_type_id
 * @property string|null $org_src Original video
 * @property string|null $stream_src Stream video
 * @property string|null $representations
 * @property int|null $stream_status_id
 * @property int|null $stream_percentage
 * @property string $stream_status_comment [varchar(255)]
 * @property int|null $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int $media_size [bigint(20) unsigned]
 * @property bool $has_org_src [tinyint(1)]
 * @property bool $has_streamed_src [tinyint(1)]
 * @property int $queue_id [int(11)]
 * @property int $media_duration [int(11)]
 * @property int $rejisyor_id [int(11)]
 * @property int $quality_id
 * @property int $is_test
 * @property int $published_at
 *
 * @property FilmCategory $category
 * @property User $createdBy
 * @property FilmType $type
 * @property-read Queue $queue
 * @property-read string $fullName
 * @property-read string $typeName
 * @property-read string $countInfo
 * @property-read string $countryName
 * @property-read Country $country
 * @property-read string $categoryName
 * @property-read \common\modules\film\models\Actor $rejisyor
 * @property-read string $qualityText
 * @property User $updatedBy
 * @property FavoriteFilm $userFavoriteFilm
 */
class Film extends ActiveRecord
{

    use FilmImageTrait;
    use FilmOriginalVideoTrait;
    use FilmStreamTrait;
    use FilmMediaSourceTrait;
    use FilmSerialTypeTrait;
    use FilmPriceTypeTrait;
    use FilmLikeDislikeTrait;
    use FilmGenreTrait;
    use FilmActorTrait;
    use FilmCommentTrait;
    use FilmLastSeenTrait;
    use FilmViewsTrait;
    use FavoriteFilmTrait;
    use FilmSerialPartsTrait;
    use FilmGalleryTrait;
    use FilmAccessTrait;
    use FilmTestStatusTrait;

    //<editor-fold desc="Constants" defaultstate="collapsed">

//    const BASE_ORIGINAL_URL = '/uploads/media/orginal';
    const BASE_ORIGINAL_URL = '/uploads/media/disk2/orginal';
//    const BASE_STREAM_URL = '/uploads/media/stream';
    const BASE_STREAM_URL = '/uploads/media/disk2/stream';

    const SCENARIO_UPLOAD_VIDEO = 'uploadVideo';

    /**
     * Video yuklangandan keyin hal stream qilmasdan avval stream_statusi qiymati
     */
    const NO_STREAM = 3;

    /**
     * Video yuklangandan keyin hal stream qilmasdan avval stream_statusi qiymati
     */
    const IN_QUEUE = 4;

    /**
     * Video stream qilinayotgan paytdagi stream_status qiymati
     */
    const IS_STREAMING = 5;

    /**
     * Video stream tugagandan keyingi stream_status qiymati
     */
    const STREAM_FINISHED = 6;

    /**
     * Video stream qilishda xatolik yuz berdi
     */
    const STREAM_ERROR = 9;

    const REPRESENTATIONS = [720, 360];

    const DEFAULT_REPRESENTATION = 720;

    /**
     * Serial bo'lmagan oddiy kinolar
     */
    const SERIAL_TYPE_SINGLE = 1;

    /**
     * Seriallar
     */
    const SERIAL_TYPE_SERIAL = 2;

    /**
     * Serial ichidagi qismlar
     */
    const SERIAL_TYPE_PART = 3;

    const PRICE_TYPE_FREE = 1;
    const PRICE_TYPE_PREMIUM = 2;

    const FILM_IS_FHD = 1;
    const FILM_IS_NOT_FHD = 0;

    //</editor-fold>

    public $film_genres;
    public $film_actors;

    //<editor-fold desc="Parent" defaultstate="collapsed">

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'film';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'serial_type_id', 'published_at'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['year', 'category_id', 'type_id', 'country_id', 'serial_type_id', 'season_id', 'price_type_id', 'status', 'rejisyor_id', 'quality_id', 'sort_order'], 'integer'],
            [['year', 'category_id', 'type_id', 'country_id', 'serial_type_id', 'season_id', 'price_type_id', 'status', 'season_id', 'is_test'], 'integer'],
            [['film_genres', 'film_actors'], 'safe'],
            [['category_id'], 'exist', 'skipOnError' => true, 'targetClass' => FilmCategory::className(), 'targetAttribute' => ['category_id' => 'id']],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Film::className(), 'targetAttribute' => ['parent_id' => 'id']],
            [['type_id'], 'exist', 'skipOnError' => true, 'targetClass' => FilmType::className(), 'targetAttribute' => ['type_id' => 'id']],
            [['image'], 'image', 'maxSize' => 1024 * 1024 * 2],
            ['org_src', 'required', 'on' => self::SCENARIO_UPLOAD_VIDEO],
            ['org_src', 'file', 'mimeTypes' => 'video/*', 'on' => self::SCENARIO_UPLOAD_VIDEO, 'maxSize' => self::maxVideoSize()],
            ['stream_status_id', 'default', 'value' => Film::NO_STREAM],
            ['price_type_id', 'in', 'range' => Film::priceTypeKeys()],
            ['stream_status_id', 'default', 'value' => Film::NO_STREAM],
            [['sort_order'], 'default', 'value' => 99999],
            ['published_at', 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [

            'film' => [
                'class' => FilmBehavior::className(),
            ],
            'timestamp' => [
                'class' => TimestampBehavior::class,
            ],
            'blameable' => [
                'class' => BlameableBehavior::class,
            ],
            'multilingual' => [
                'class' => MultilingualBehavior::class,
                'attributes' => ['name', 'description'],
            ],
            'slug' => [
                'class' => FilmSluggableBehavior::class,
            ],
            'galleryBehavior' =>
                [
                    'class' => GalleryBehavior::class,
                    'type' => 'film',
                    'extension' => 'jpg',
                    'directory' => Yii::getAlias('@frontend/web/uploads') . '/images/filmGallery',
                    'url' => '/uploads/images/filmGallery',
                    'versions' => [
                        'preview' => function ($img) {
                            /** @var \Imagine\Image\ImageInterface $img */
                            $dstSize = $img->getSize();
                            $dstSize = $dstSize->widen(200);
                            return $img
                                ->copy()
                                ->resize($dstSize);
                        },
                        'original' => function ($img) {
                            /** @var \Imagine\Image\ImageInterface $img */
                            $dstSize = $img->getSize();
                            $maxWidth = 800;
                            if ($dstSize->getWidth() > $maxWidth) {
                                $dstSize = $dstSize->widen($maxWidth);
                            }
                            return $img
                                ->copy()
                                ->resize($dstSize);
                        },
                    ]
                ],
            'image' => [
                'class' => UploadImageBehavior::class,
                'attribute' => 'image',
                'scenarios' => ['default'],
                'path' => '@frontend/web/uploads/images/film/{id}',
                'url' => '/uploads/images/film/{id}',
                'deleteOriginalFile' => true,
                'thumbs' => [
                    'preview' => ['width' => 1440],
                    'thumb' => ['width' => 300, 'quality' => 90],
                ],
            ],
            [
                'class' => TimestampConvertorBehavior::class,
                'attribute' => 'published_at'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function labels()
    {
        return [
            'slug' => 'Slug',
            'year' => 'Yili',
            'image' => 'Rasm',
            'sort_order' => 'Tartib raqami',
            'category_id' => 'Film kategoriyasi',
            'type_id' => 'Film turi',
            'type.name' => 'Film turi',
            'country_id' => 'Mamlakat',
            'serial_type_id' => 'Serial turi',
            'serialTypeName' => 'Serial turi',
            'country.name' => 'Davlat',
            'parent_id' => 'Serial',
            'parent.name' => 'Serial',
            'season_id' => 'Fasl',
            'season.name' => 'Fasl',
            'price_type_id' => 'Narx turi',
            'priceTypeName' => 'Narx turi',
            'priceTypeLabel' => 'Narx turi',
            'org_src' => 'Original video',
            'stream_src' => 'Stream video',
            'representations' => 'Representations',
            'stream_percentage' => 'Stream Foiz',
            'film_genres' => 'Film janri',
            'badgeGenreAssign' => 'Film janrlari',
            'film_actors' => 'Film qatnashchilari',
            'countInfo' => 'Raqamlar',
            'streamStatusName' => 'Video holati',
            'stream_status_id' => 'Video holati',
            'media_duration' => 'Davomiyligi',
            'media_size' => 'Hajmi',
            'has_org_src' => 'Original video bor',
            'rejisyor_id' => 'Rejisyor',
            'partsCount' => 'Qismlar soni',
            'activePartsCount' => 'Faol qismlar soni',
            'quality_id' => 'FHD',
            'qualityText' => 'Film sifati',
            'seasonsCount' => 'Fasllar soni',
            'is_test' => 'Test uchunmi?',
            'published_at' => "E'lon qilinish sanasi"

        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_UPLOAD_VIDEO] = [];
        return $scenarios;
    }

    /**
     * {@inheritdoc}
     * @return FilmQuery the active query used by this AR class.
     */
    public static function find()
    {
        $query = new FilmQuery(get_called_class());
        return $query->multilingual();
    }

    //</editor-fold>


    //<editor-fold desc="Relations" defaultstate="collapsed">

    /**
     * @return ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(FilmCategory::className(), ['id' => 'category_id']);
    }

    /**
     * @return UserQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'created_by']);
    }

    /**
     * @return ActiveQuery
     */
    public function getType()
    {
        return $this->hasOne(FilmType::className(), ['id' => 'type_id']);
    }

    /**
     * @return UserQuery
     */
    public function getUpdatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'updated_by']);
    }

    /**
     * @return ActiveQuery
     */
    public function getQueue()
    {
        return $this->hasOne(Queue::class, ['id' => 'queue_id']);
    }

    /**
     * @return \soft\db\ActiveQuery
     */
    public function getCountry()
    {
        return $this->hasOne(Country::className(), ['id' => 'country_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getRejisyor()
    {
        return $this->hasOne(Actor::class, ['id' => 'rejisyor_id']);
    }
    //</editor-fold>

    //<editor-fold desc="Additional" defaultstate="collapsed">

    /**
     * @param $nonPartial bool
     * @return array
     */
    public static function map(bool $nonPartial = true): array
    {
        $query = static::find();
        if ($nonPartial) {
            $query->nonPartial();
        }
        return ArrayHelper::map($query->all(), 'id', 'name');
    }

    /**
     * @return string
     */
    public function getFullName()
    {
        if ($this->getIsPartial()) {
            return $this->parent->name . '. ' . $this->name;
        }
        return $this->name;
    }

    /**
     * @return string
     */
    public function getTypeName()
    {
        return $this->type ? $this->type->name : '';
    }

    /**
     * @return string
     */
    public function getCountryName()
    {
        return $this->country ? $this->country->name : '';
    }

    /**
     * @return string
     */
    public function getCategoryName()
    {
        return $this->category ? $this->category->name : '';
    }

    /**
     * @return string
     */
    public function getCountInfo()
    {
        $likes = Html::withIcon($this->likesCount, 'thumbs-up,far');
        $dislikes = Html::withIcon($this->dislikesCount, 'thumbs-down,far');
        $comments = Html::withIcon($this->commentsCount, 'comments,far');
        $views = Html::withIcon($this->viewsCount, 'eye');
        $info = $likes . '&nbsp;&nbsp;' . $dislikes . '&nbsp;&nbsp;' . $comments . '&nbsp;&nbsp;' . $views;

        if ($this->getIsSerial()) {
            $info .= '&nbsp;&nbsp;' . Html::withIcon($this->partsCount, 'list-ol', ['title' => 'Qismlar soni']);
        }
        return $info;
    }

    //</editor-fold>]

    /**
     * @return string[]
     */
    public static function qualitys()
    {
        return [
            self::FILM_IS_FHD => 'FHD',
            self::FILM_IS_NOT_FHD => '',
        ];
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getQualityText()
    {
        return ArrayHelper::getValue(self::qualitys(), $this->quality_id);
    }

    /**
     * @return bool
     */
    public function getIsUserLike(): bool
    {
        if (is_guest()) {
            return false;
        }
        return (bool)LikeDislike::find()
            ->andWhere(['film_id' => $this->id, 'user_id' => user('id'), 'type_id' => LikeDislike::TYPE_LIKE])
            ->one();
    }

    /**
     * @return bool
     */
    public function getIsUserDisLike(): bool
    {
        if (is_guest()) {
            return false;
        }

        return (bool)LikeDislike::find()
            ->andWhere(['film_id' => $this->id, 'user_id' => user('id'), 'type_id' => LikeDislike::TYPE_DISLIKE])
            ->one();
    }

    /**
     * @param $parent_id
     * @return array
     */
    public static function getChild($parent_id = []): array //TODO optimallashtirish kerak
    {
        $items = self::find()
            ->select('id')
            ->andWhere(['parent_id' => $parent_id])
            ->asArray()
            ->all();
        $ids = [];

        foreach ($items as $item) {
            $ids[] = $item['id'];
            $ids = array_merge($ids, self::getChild($item['id']));
        }
        return $ids;
    }
}

?>
