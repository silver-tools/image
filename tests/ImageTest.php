<?php
declare(strict_types=1);

namespace SilverImage\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SilverImage\Image;

final class ImageTest extends TestCase
{
    /**
     * Test calc resized image size.
     *
     * @return void
     */
    public function testCalcResizedImageSize()
    {
        $calcResizedImageSize = new ReflectionMethod(Image::class, 'calcResizedImageSize');
        $calcResizedImageSize->setAccessible(true);

        $imageStr = $this->makeImageString(['width' => 400, 'height' => 300]);
        $image = new Image($imageStr);

        [$distWidth, $distHeight] = $calcResizedImageSize->invoke($image, ['width' => 200]);
        $this->assertSame($distWidth, 200);
        $this->assertSame($distHeight, 150);

        [$distWidth, $distHeight] = $calcResizedImageSize->invoke($image, ['height' => 100]);
        $this->assertSame($distWidth, 100);
        $this->assertSame($distHeight, 75);
    }

    /**
     * Test constructor.
     *
     * @dataProvider constructExceptionProvider()
     * @param mixed $data Value to be specified for Image class arguments.
     * @param string $exception Exception class name.
     * @return void
     */
    public function testConstruct($data, $exception)
    {
        $this->expectException($exception);
        new Image($data);
    }

    /**
     * Test get image type.
     *
     * @return void
     */
    public function testGetImageType()
    {
        $getImageType = new ReflectionMethod(Image::class, 'getImageType');
        $getImageType->setAccessible(true);

        $typeList = [
            'gif' => IMAGETYPE_GIF,
            'jpg' => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'png' => IMAGETYPE_PNG,
            'webp' => IMAGETYPE_WEBP,
        ];

        $imageStr = $this->makeImageString();
        $image = new Image($imageStr);
        foreach ($typeList as $ext => $type) {
            $this->assertSame(
                $getImageType->invoke($image, $ext),
                $type
            );
        }
    }

    /**
     * Test rotate.
     *
     * @return void
     */
    public function testRotate()
    {
        $imageStr = $this->makeImageString(['width' => 400, 'height' => 300]);
        $image = new Image($imageStr);

        $image->rotate(90);

        $this->assertSame($image->width, 300);
        $this->assertSame($image->height, 400);
    }

    // *********************************************************
    // * Data Providers
    // *********************************************************

    /**
     * Data provider for testConstruct()
     *
     * @return array
     */
    public function constructExceptionProvider()
    {
        return [
            [null, \ErrorException::class],
            ['a', \ErrorException::class],
        ];
    }

    // *********************************************************
    // * Private user-defined functions
    // *********************************************************

    /**
     * Create dummy image binary string (PNG)
     *
     * @param array $options Options.
     * @return string
     */
    private function makeImageString($options = [])
    {
        $options += [
            'width' => 10,
            'height' => 10,
        ];

        $im = imagecreate($options['width'], $options['height']);
        imagecolorallocate($im, 0, 0, 0);
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }
}
