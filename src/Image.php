<?php
declare(strict_types=1);

/**
 * Silver Image
 *
 * @license MIT License
 */
namespace SilverImage;

class Image
{
    /**
     * @var string|null Filename.
     */
    public $basename;

    /**
     * @var string The image file in a string.
     */
    private $contents;

    /**
     * @var string|null parent directory's path of the image file.
     */
    public $dirname;

    /**
     * @var string|null Extension of the image file.
     */
    public $extension;

    /**
     * @var string|null File name of the image file.
     */
    public $filename;

    /**
     * @var int Height of the image.
     */
    public $height;

    /**
     * @var \GdImage|resource|false The image.
     */
    public $image;

    /**
     * @var int The image type.
     */
    public $imagetype;

    /**
     * @var string MIME Content-type.
     */
    public $mime;

    /**
     * @var string Canonicalized absolute path of the image.
     */
    public $realpath;

    /**
     * @var int Width of the image.
     */
    public $width;

    /**
     * Constructor
     *
     * @param mixed $data File path, image data string, or stream.
     * @throws \ErrorException When argument is invalid, or could not get the image info.
     */
    public function __construct($data)
    {
        if (!is_string($data) && !is_resource($data)) {
            throw new \ErrorException();
        }

        $isFile = (is_string($data) && is_file($data));

        $this->contents = $data;
        if ($isFile) {
            $this->contents = file_get_contents($data);
        } elseif (is_resource($data)) {
            $this->contents = stream_get_contents($data);
        }

        // phpcs:ignore
        $size = @getimagesizefromstring($this->contents);
        if ($size === false) {
            throw new \ErrorException();
        }

        $this->width = $size[0];
        $this->height = $size[1];
        $this->imagetype = $size[2];
        $this->mime = $size['mime'];

        if ($isFile) {
            $this->realpath = realpath($data);
            $info = pathinfo($data);
            foreach ($info as $name => $value) {
                $this->{$name} = $value;
            }
        }

        $this->image = imagecreatefromstring($this->contents);
    }

    // *********************************************************
    // * Public functions
    // *********************************************************

    /**
     * Resize
     *
     * @param array $options Options.
     * @return self|false Resized image object.
     */
    public function resize(array $options)
    {
        $options += [
            'mode' => 'clip',
            'width' => null,
            'height' => null,
        ];

        // Mode
        $modes = ['clip'];
        $mode = $options['mode'];
        if (!in_array($mode, $modes)) {
            return false;
        }

        // Calculate resized image size
        [$dstWidth, $dstHeight] = $this->calcResizedImageSize($options);

        // Resize
        $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
        imagecopyresampled($dstImage, $this->image, 0, 0, 0, 0, $dstWidth, $dstHeight, $this->width, $this->height);
        $this->image = $dstImage;
        $this->width = $dstWidth;
        $this->height = $dstHeight;

        return $this;
    }

    /**
     * Rotate
     *
     * @param float $angle Rotation angle.
     * @param int $bgColor Background color of the uncovered zone.
     * @return $this
     */
    public function rotate(float $angle, int $bgColor = 0)
    {
        $this->image = imagerotate($this->image, $angle, $bgColor);
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);

        return $this;
    }

    /**
     * Save to file
     *
     * @param string|null $path When null, save to "realpath".
     * @return \SilverImage\Image|false
     */
    public function save(?string $path = null)
    {
        $path = $path ?? $this->realpath;
        if (!is_string($path)) {
            throw new \Exception();
        }

        $info = pathinfo($path);
        $isDir = !isset($info['extension']);

        $basename = $isDir ? $this->basename : $info['basename'];
        if (!$basename) {
            return false;
        }

        $dirname = $isDir ? $path : $info['dirname'];
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            return false;
        }

        $extension = $isDir ? $this->extension : $info['extension'];
        $imagetype = $this->getImagetype($extension);
        if ($imagetype === false) {
            return false;
        }

        if (!$this->outputImage($path, $imagetype)) {
            return false;
        }

        $newImage = clone $this;
        $newImage->basename = $basename;
        $newImage->dirname = $dirname;
        $newImage->extension = $extension;
        $newImage->filename = $isDir ? $this->filename : $info['filename'];
        $newImage->imagetype = $imagetype;
        $newImage->realpath = realpath($path);

        return $newImage;
    }

    // *********************************************************
    // * Private functions
    // *********************************************************

    /**
     * Calculate width and height
     *
     * @param array $options Options.
     * @return array
     */
    protected function calcResizedImageSize($options)
    {
        $options += [
            'height' => null,
            'width' => null,
            'mode' => 'clip',
        ];

        $optWidth = $options['width'];
        $optHeight = $options['height'];
        if ($optWidth === null && $optHeight) {
            $optWidth = $optHeight;
        } elseif ($optHeight === null && $optWidth) {
            $optHeight = $optWidth;
        }

        $isWide = ($this->width > $this->height);
        $isOptWide = ($optWidth > $optHeight);

        $distWidth = null;
        $distHeight = null;
        switch ($options['mode']) {
            case 'clip':
                if ($isWide || $isOptWide) {
                    $distWidth = $optWidth;
                    $distHeight = (int)round($this->height * $distWidth / $this->width);
                } else {
                    $distHeight = $optHeight;
                    $distWidth = (int)round($this->width * $distHeight / $this->height);
                }
                break;
        }

        return [$distWidth, $distHeight];
    }

    /**
     * Get imagetype from extension
     *
     * @param string $extension Extension.
     * @return int|false
     */
    protected function getImagetype($extension)
    {
        switch ($extension) {
            case 'gif':
                return IMAGETYPE_GIF;
            case 'jpg':
            case 'jpeg':
                return IMAGETYPE_JPEG;
            case 'png':
                return IMAGETYPE_PNG;
            case 'webp':
                return IMAGETYPE_WEBP;
        }

        return false;
    }

    /**
     * Output image
     *
     * @param string $path The file path.
     * @param int $imagetype The imagetype.
     * @return bool
     */
    protected function outputImage($path, $imagetype)
    {
        switch ($imagetype) {
            case IMAGETYPE_GIF:
                return imagegif($this->image, $path);
            case IMAGETYPE_JPEG:
                return imagejpeg($this->image, $path);
            case IMAGETYPE_PNG:
                return imagepng($this->image, $path);
            case IMAGETYPE_WEBP:
                return imagewebp($this->image, $path);
        }

        return false;
    }
}
