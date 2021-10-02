<?php

namespace App\Http\Requests;

use App\Actions\AlbumAuthorisationProvider;
use App\Actions\PhotoAuthorisationProvider;
use App\Contracts\InternalLycheeException;
use App\Contracts\LycheeException;
use App\Exceptions\UnauthorizedException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

abstract class BaseApiRequest extends FormRequest
{
	protected AlbumAuthorisationProvider $albumAuthorisationProvider;
	protected PhotoAuthorisationProvider $photoAuthorisationProvider;

	public function __construct(
		array $query = [],
		array $request = [],
		array $attributes = [],
		array $cookies = [],
		array $files = [],
		array $server = [],
		$content = null
	) {
		$this->albumAuthorisationProvider = resolve(AlbumAuthorisationProvider::class);
		$this->photoAuthorisationProvider = resolve(PhotoAuthorisationProvider::class);
		parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
	}

	/**
	 * Validate the class instance.
	 *
	 * Fixes another Laravel stupidity.
	 * We must **first** validate the input parameters of the request
	 * for syntactical correctness, and **then** authorize the request.
	 * Rationale: Whether a user is authorized to perform a specific action or
	 * not typically depend on the input parameters (e.g. the ID of the model,
	 * the property the user wants to change, the new value of the property,
	 * etc.).
	 * Hence, the input should be validated **before** a potential DB query is
	 * executed to determine the user's authorization.
	 * The original Laravel method tries to authorize the user first and
	 * then validate the request
	 * (see {@link \Illuminate\Validation\ValidatesWhenResolvedTrait::validateResolved()}).
	 *
	 * @return void
	 *
	 * @throws BindingResolutionException
	 * @throws ValidationException
	 * @throws UnauthorizedException
	 * @throws BadRequestException
	 */
	public function validateResolved(): void
	{
		// 1. Validate the request
		$this->prepareForValidation();
		$instance = $this->getValidatorInstance();
		if ($instance->fails()) {
			// the default implementation throws `ValidationException`
			$this->failedValidation($instance);
		}
		$this->passedValidation();

		// 2. Authorize the request
		if (!$this->passesAuthorization()) {
			$this->failedAuthorization();
		}
	}

	/**
	 * Called by the framework after successful input validation.
	 *
	 * Simply forwards the call to {@link BaseApiRequest::processValidatedValues()}
	 * of the child class.
	 *
	 * @throws ValidationException
	 * @throws BadRequestException
	 */
	protected function passedValidation()
	{
		$this->processValidatedValues($this->validated(), $this->allFiles());
	}

	/**
	 * Handles a failed authorization attempt.
	 *
	 * Always throws {@link UnauthorizedException}.
	 *
	 * @return void
	 *
	 * @throws UnauthorizedException always thrown
	 */
	protected function failedAuthorization(): void
	{
		throw new UnauthorizedException();
	}

	/**
	 * Determines of the user is authorized to access the designated album.
	 *
	 * @param string|int|null $albumID the ID of the album
	 *
	 * @return bool true, if the authenticated user is authorized
	 *
	 * @throws InternalLycheeException
	 */
	protected function authorizeAlbumAccess($albumID): bool
	{
		return $this->albumAuthorisationProvider->isAccessible($albumID);
	}

	/**
	 * Determines of the user is authorized to modify or write into the
	 * designated albums.
	 *
	 * @param array $albumIDs the IDs of the albums
	 *
	 * @return bool true, if the authenticated user is authorized
	 *
	 * @throws InternalLycheeException
	 */
	protected function authorizeAlbumWrite(array $albumIDs): bool
	{
		return $this->albumAuthorisationProvider->areEditable($albumIDs);
	}

	/**
	 * Determines of the user is authorized to see the designated photo.
	 *
	 * @param int $photoID the ID of the photo
	 *
	 * @return bool true, if the authenticated user is authorized
	 *
	 * @throws InternalLycheeException
	 */
	protected function authorizePhotoVisible(int $photoID): bool
	{
		return $this->photoAuthorisationProvider->isVisible($photoID);
	}

	/**
	 * Determines of the user is authorized to modify the designated photos.
	 *
	 * @param int[] $photoIDs the IDs of the photos
	 *
	 * @return bool true, if the authenticated user is authorized
	 *
	 * @throws InternalLycheeException
	 */
	protected function authorizePhotoWrite(array $photoIDs): bool
	{
		return $this->photoAuthorisationProvider->areEditable($photoIDs);
	}

	/**
	 * Converts the input value to a boolean.
	 *
	 * Opposed to trivial type-casting the conversion also correctly recognizes
	 * the inputs `0`, `1`, `'0'`, `'1'`, `'true'` and `'false'`.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	protected static function toBoolean($value): bool
	{
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Determines if the user is authorized to make this request.
	 *
	 * @return bool
	 *
	 * @throws LycheeException
	 */
	abstract public function authorize(): bool;

	/**
	 * Returns the validation rules that apply to the request.
	 *
	 * @return array
	 */
	abstract public function rules(): array;

	/**
	 * Post-processes the validated values.
	 *
	 * @param array          $values
	 * @param UploadedFile[] $files
	 *
	 * @return void
	 */
	abstract protected function processValidatedValues(array $values, array $files): void;
}