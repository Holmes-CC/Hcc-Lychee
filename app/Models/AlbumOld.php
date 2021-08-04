<?php

/** @noinspection PhpUndefinedClassInspection */

namespace App\Models;

use App\Assets\HasManyBidirectionally;
use App\Contracts\AlbumInterface;
use App\Models\Extensions\AlbumBooleans;
use App\Models\Extensions\AlbumCast;
use App\Models\Extensions\AlbumGetters;
use App\Models\Extensions\AlbumSetters;
use App\Models\Extensions\AlbumStringify;
use App\Models\Extensions\CustomSort;
use App\Models\Extensions\HasBidirectionalRelationships;
use App\Models\Extensions\HasTimeBasedID;
use App\Models\Extensions\NodeTrait;
use App\Models\Extensions\UTCBasedTimes;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * App\Album.
 *
 * @property int         $id
 * @property string      $title
 * @property int         $owner_id
 * @property int|null    $parent_id
 * @property string      $description
 * @property Carbon|null $min_taken_at
 * @property Carbon|null $max_taken_at
 * @property int         $public
 * @property int         $full_photo
 * @property int         $viewable
 * @property int         $downloadable
 * @property int         $share_button_visible
 * @property string|null $password
 * @property string      $license
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property Collection  $children
 * @property User        $owner
 * @property Album       $parent
 * @property Collection  $photos
 * @property Photo|null  $cover
 */
class AlbumOld extends Model implements AlbumInterface
{
	use NodeTrait;
	use AlbumBooleans;
	use AlbumStringify;
	use AlbumGetters;
	use AlbumCast;
	use AlbumSetters;
	use CustomSort;
	use UTCBasedTimes;
	use HasTimeBasedID;
	use HasBidirectionalRelationships;

	/**
	 * Indicates if the model's primary key is auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	protected $casts
	= [
		'public' => 'int',
		'nsfw' => 'int',
		'viewable' => 'int',
		'downloadable' => 'int',
		'share_button_visible' => 'int',
		'created_at' => 'datetime',
		'updated_at' => 'datetime',
		'min_taken_at' => 'datetime',
		'max_taken_at' => 'datetime',
	];

	/**
	 * The relationships that should always be eagerly loaded by default.
	 */
	protected $with = ['owner', 'cover'];

	/**
	 * This method is called by the framework after the model has been
	 * booted.
	 *
	 * This method alters the default query builder for this model and
	 * adds a "scope" to the query builder in order to add the "virtual"
	 * columns `max_taken_at` and `min_taken_at` to every query.
	 */
	protected static function booted()
	{
		parent::booted();
		// Normally "scopes" are used to restrict the result of the query
		// to a particular subset through adding additional WHERE-clauses
		// to the default query.
		// However, "scopes" can be used to manipulate the query in any way.
		// Here we add to additional "virtual" columns to the query.
		static::addGlobalScope('add_minmax_taken_at', function (Builder $builder) {
			$builder->addSelect([
				'max_taken_at' => Photo::query()
					->select('taken_at')
					->leftJoin('albums as a', 'a.id', '=', 'album_id')
					->whereColumn('a._lft', '>=', 'albums._lft')
					->whereColumn('a._rgt', '<=', 'albums._rgt')
					->whereNotNull('taken_at')
					->orderBy('taken_at', 'desc')
					->limit(1),
				'min_taken_at' => Photo::query()
					->select('taken_at')
					->leftJoin('albums as a', 'a.id', '=', 'album_id')
					->whereColumn('a._lft', '>=', 'albums._lft')
					->whereColumn('a._rgt', '<=', 'albums._rgt')
					->whereNotNull('taken_at')
					->orderBy('taken_at', 'asc')
					->limit(1),
			]);
		});
	}

	/**
	 * Return the relationship between Photos and their Album.
	 *
	 * @return HasManyBidirectionally
	 */
	public function photos(): HasManyBidirectionally
	{
		return $this->hasManyBidirectionally(Photo::class);
	}

	/**
	 * Return the relationship between an album and its owner.
	 *
	 * @return BelongsTo
	 */
	public function owner(): BelongsTo
	{
		return $this->belongsTo('App\Models\User', 'owner_id', 'id');
	}

	/**
	 * Return the relationship between an album and its sub albums.
	 *
	 * Note: Actually, the return type should be non-nullable.
	 * However, {@link \App\SmartAlbums\BareSmartAlbum} extends this class and
	 * {@link \App\SmartAlbums\SmartAlbum::children()} cannot return an
	 * correctly instantiated object of `HasMany` but must return `null`,
	 * because a `SmartAlbum` is not a real Eloquent model and does not exist
	 * as a database entity.
	 * TODO: Refactor the inheritance relationships of all album types.
	 * A `SmartAlbum` (which cannot have sub-albums} should not inherit from
	 * `Album`.
	 * Instead both kind of albums should share an interface.
	 * Then the return type of this method could be repaired.
	 *
	 * @return ?HasMany
	 */
	public function children(): ?HasMany
	{
		return $this->hasMany('App\Models\Album', 'parent_id', 'id');
	}

	/**
	 * Return the relationship between an album and its cover.
	 *
	 * @return HasOne
	 */
	public function cover(): HasOne
	{
		return $this->hasOne('App\Models\Photo', 'id', 'cover_id');
	}

	/**
	 * Return the relationship between an album and its parent.
	 *
	 * @return BelongsTo
	 */
	public function parent(): BelongsTo
	{
		return $this->belongsTo('App\Models\Album', 'parent_id', 'id');
	}

	/**
	 * @return BelongsToMany
	 */
	public function shared_with(): BelongsToMany
	{
		return $this->belongsToMany(
			'App\Models\User',
			'user_album',
			'album_id',
			'user_id'
		);
	}

	/**
	 * Before calling delete() to remove the album from the database
	 * we need to go through each sub album and delete it.
	 * Idem we also delete each pictures inside an album (recursively).
	 *
	 * @return bool|null
	 *
	 * @throws Exception
	 */
	public function predelete()
	{
		$no_error = true;
		$photos = $this->get_all_photos()->get();
		foreach ($photos as $photo) {
			$no_error &= $photo->predelete();
			$no_error &= $photo->delete();
		}

		return $no_error;
	}

	/**
	 * Return the full path of the album consisting of all its parents' titles.
	 *
	 * @return string
	 */
	public static function getFullPath($album)
	{
		$title = [$album->title];
		$parentId = $album->parent_id;
		while ($parentId) {
			$parent = Album::find($parentId);
			array_unshift($title, $parent->title);
			$parentId = $parent->parent_id;
		}

		return implode('/', $title);
	}

	/**
	 * Setter/Mutator for attribute `min_taken_at`.
	 *
	 * Actually, this method should be a no-op and throw an exception.
	 * The attribute `min_taken_at` is a transient attribute of the model
	 * and cannot be persisted to database.
	 * It is calculated by the DB back-end upon fetching the model.
	 * Hence, it wrong to try to set this attribute.
	 * However, {@link AlbumCast::toTagAlbum()} does it nonetheless, so we
	 * don't throw an exception until that method is fixed.
	 *
	 * TODO: Fix {@link AlbumCast::toTagAlbum()}.
	 *
	 * @param Carbon|null $value
	 */
	protected function setMinTakenAtAttribute(?Carbon $value): void
	{
		// Uncomment the following line, after AlbumCast::toTagAlbum() has been fixed
		//throw new \BadMethodCallException('Attribute "min_taken_at" must not be set as it is a virtual attribute');
	}

	/**
	 * Setter/Mutator for attribute `max_taken_at`.
	 *
	 * Actually, this method should be a no-op and throw an exception.
	 * The attribute `max_taken_at` is a transient attribute of the model
	 * and cannot be persisted to database.
	 * It is calculated by the DB back-end upon fetching the model.
	 * Hence, it wrong to try to set this attribute.
	 * However, {@link AlbumCast::toTagAlbum()} does it nonetheless, so we
	 * don't throw an exception until that method is fixed.
	 *
	 * TODO: Fix {@link AlbumCast::toTagAlbum()}.
	 *
	 * @param Carbon|null $value
	 */
	protected function setMaxTakenAtAttribute(?Carbon $value): void
	{
		// Uncomment the following line, after AlbumCast::toTagAlbum() has been fixed
		//throw new \BadMethodCallException('Attribute "max_taken_at" must not be set as it is a virtual attribute');
	}
}
