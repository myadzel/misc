loadplugin("rotate.dll")

v1 = DirectShowSource("00002.mts", 25.000)

v2 = DirectShowSource("00003.mts", 25.000)

v1++v2

rotate(180.0)