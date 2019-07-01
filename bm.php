<?php

function read_bmp($file)
{
    $data = file_get_contents($file);

    $header = unpack("c_B/c_M/L_size/s_unused1/s_unused2/L_offset", $data);
    if($header["_B"] !== ord("B") || $header["_M"] !== ord("M")) // if first 2 bytes not 'B''M', means not a bmp file.
        return false;
    //var_dump($header);

    $offset = $header["_offset"];
    $data = substr($data, 14);
    $offset -= 14;
    $info = unpack("L_info_size/L_width/L_height/S_plane/S_bit/L_compress/L_size/L_x_ppm/L_y_ppm/L_color_table_used/L_important", $data);
    //var_dump($info);

    $data = substr($data, $info["_info_size"]);
    $offset -= $info["_info_size"];
    $color_table_map = [
        1 => 2,
        4 => 16,
        8 => 256,
    ];
    $bit = $info["_bit"];
    $color_table = [];
    if($bit < 24) // if bits is less than 24(1, 4, 8), pixel data is index, so it has a color table. length of color table based on bit count.
    {
        $num_color_table = $color_table_map[$bit];
        for($i = 0; $i < $num_color_table; $i++)
        {
            $color_table[] = unpack("C_B/C_G/C_R/C_reserved", $data);
            $data = substr($data, 4);
            $offset -= 4;
        }
        //var_dump($color_table);
    }

    $pixels = [];
    $ppb_map = [
        1 => 8,
        4 => 2,
        8 => 1,

        24 => 3,
        32 => 4,
    ];
    $width = $info["_width"];
    $height = $info["_height"];
    $size = $width * $height;
    $wi = 0;
    $hi = $height - 1; // height is from bottom to top
    $read = 0;
    if($bit < 24)
    {
        for($i = 0; $i < $size; $i += $ppb_map[$bit])
        {
            $index = unpack("C_i", $data)["_i"];
            $read++;
            $data = substr($data, 1);
            for($m = 0; $m < $ppb_map[$bit]; $m++)
            {
                $pixels[$hi][$wi] = (($index << ($m * $bit)) & 0xff) >> (8 - $bit);
                if($wi >= $width - 1)
                {
                    $wi = 0;
                    $hi--;
                    while($read % 4 !== 0) // a line pixel 4 bytes alignment
                    {
                        $data = substr($data, 1);
                        $read++;
                    }
                    continue;
                }
                else
                    $wi++;
            }
        }
    }
    else
    {
        for($i = 0; $i < $size; $i++)
        {
            $color = unpack($bit === 32 ? "C_B/C_G/C_R/C_A" : "C_B/C_G/C_R", $data); // 32bits pixel color include alpha byte
            $read += $ppb_map[$bit];
            $pixels[$hi][$wi] = [
                $color["_R"], $color["_G"], $color["_B"], $bit === 32 ? $color["_A"] : 255,
            ];
            $data = substr($data, $ppb_map[$bit]);
            if($wi >= $width - 1)
            {
                $wi = 0;
                $hi--;
                while($read % 4 !== 0) // a line pixel 4 bytes alignment
                {
                    $data = substr($data, 1);
                    $read++;
                }
            }
            else
                $wi++;
        }
    }
    //var_dump($pixels);

    return [
      "header" => $header,
        "info" => $info,
        "color_table" => $color_table,
        "pixels" => $pixels,
    ];
    //var_dump($read);
}
