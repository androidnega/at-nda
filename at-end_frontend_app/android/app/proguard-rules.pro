# tflite_flutter references optional TensorFlow Lite GPU APIs; R8 reports them as missing without the GPU AAR.
-dontwarn org.tensorflow.lite.gpu.GpuDelegateFactory$Options
-dontwarn org.tensorflow.lite.gpu.**
