<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios'
import { ref, onMounted, onUnmounted } from 'vue'
import AppGuestLayout from '@/layouts/AppGuestLayout.vue'

interface VerifyPageProps {
    ageCheck: number
    codeName: string
    errors?: any
    name?: string
    auth?: any
    sidebarOpen?: boolean
    [key: string]: any
}

const page = usePage<VerifyPageProps>()

const ageCheck = page.props.ageCheck

const qrcImage = page.props.qrcImage

const codeName = page.props.codeName

const status = ref('waiting')
let interval: number
const maxTime = 30000
let elapsed = 0
const intervalTime = 2000

onMounted(() => {
    interval = window.setInterval(async () => {
        elapsed += intervalTime

        if (elapsed >= maxTime) {
            clearInterval(interval)
            window.location.href = '/noaccess'

            return
        }

        try {
            const res = await axios.get('/api/verification/checker', {
                params: { age: ageCheck, code: codeName.trim() }
            })

            if (res.data.ready) {
                status.value = res.data.result
                clearInterval(interval)

                if (status.value === 'true') {
                    window.location.href = '/login'

                } else {
                    window.location.href = '/noaccess'
                }
            }

        } catch (err) {
            console.error(err)
        }

    }, intervalTime)
})

onUnmounted(() => {
    clearInterval(interval)
})

</script>

<template>
    <AppGuestLayout>

        <Head title="Verify" />

        <div class="flex flex-col items-center justify-center h-screen bg-gray-100 space-y-6">

            <h1 class="text-2xl font-bold text-gray-800">
                Age verification for users aged {{ ageCheck }} and above
            </h1>

            <img :src="qrcImage" class="max-w-[60%] max-h-[60%]" />

        </div>

    </AppGuestLayout>
</template>
